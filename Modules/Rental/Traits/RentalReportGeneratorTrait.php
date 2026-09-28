<?php

namespace Modules\Rental\Traits;

use App\Models\Expense;
use App\Models\Module;
use App\Models\Store;
use App\Models\SubscriptionTransaction;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\TripTransaction;

trait RentalReportGeneratorTrait
{
    private function rental_provider_module_ids()
    {
        return Module::where('module_type', 'rental')->pluck('id');
    }

    private function rental_admin_expense_query($query)
    {
        return $query
            ->whereNotNull('trip_id')
            ->whereNull('store_id')
            ->where('created_by', 'admin');
    }

    private function rental_admin_earning_query($query, $module_id = 'all')
    {
        return $query
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->where('trip_transactions.module_id', $module_id);
            }, function ($builder) {
                $builder->whereIn('trip_transactions.module_id', $this->rental_provider_module_ids());
            });
    }

    private function rental_admin_subscription_query($query, $module_id = 'all')
    {
        return $query
            ->where('payment_status', 'success')
            ->where('is_trial', 0)
            ->whereHas('store.module', function ($builder) use ($module_id) {
                if (!in_array($module_id, [null, '', 'all'], true)) {
                    $builder->where('id', $module_id);
                    return;
                }

                $builder->whereIn('id', $this->rental_provider_module_ids());
            });
    }

    private function rental_provider_earning_query($query, $provider_id = 'all', $module_id = 'all')
    {
        return $query
            ->when(!in_array($provider_id, [null, '', 'all'], true), function ($builder) use ($provider_id) {
                $builder->where('trip_transactions.provider_id', $provider_id);
            })
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->where('trip_transactions.module_id', $module_id);
            }, function ($builder) {
                $builder->whereIn('trip_transactions.module_id', $this->rental_provider_module_ids());
            });
    }

    private function rental_provider_expense_query($query, $provider_id = 'all', $module_id = 'all')
    {
        return $query
            ->whereNotNull('trip_id')
            ->whereNotNull('store_id')
            ->where('created_by', 'vendor')
            ->when(!in_array($provider_id, [null, '', 'all'], true), function ($builder) use ($provider_id) {
                $builder->where('store_id', $provider_id);
            })
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->whereHas('trip', function ($tripQuery) use ($module_id) {
                    $tripQuery->where('module_id', $module_id);
                });
            }, function ($builder) {
                $builder->whereHas('trip', function ($tripQuery) {
                    $tripQuery->whereIn('module_id', $this->rental_provider_module_ids());
                });
            });
    }

    private function rental_provider_expense_totals($provider_id = 'all', $module_id = 'all', $filter = null, $from = null, $to = null, $dateRange = null)
    {
        $query = $this->rental_provider_expense_query(Expense::query(), $provider_id, $module_id);

        if ($dateRange) {
            $query->whereBetween('expenses.created_at', $dateRange);
        } else {
            $query->applyDateFilter($filter, $from, $to, 'expenses.created_at');
        }

        return $query->selectRaw("
                SUM(amount) as total_amount,
                COUNT(id) as total_count,
                SUM(CASE WHEN expenses.type = 'discount_on_trip' THEN expenses.amount ELSE 0 END) as discount_on_trip,
                SUM(CASE WHEN expenses.type = 'coupon_discount' THEN expenses.amount ELSE 0 END) as coupon_discount
            ")
            ->first();
    }

    private function rental_provider_trip_commission_query($query, $provider_id = 'all', $module_id = 'all')
    {
        return $this->rental_provider_earning_query($query, $provider_id, $module_id)
            ->where('admin_commission', '>', 0);
    }

    private function rental_provider_trip_commission_totals($provider_id = 'all', $module_id = 'all', $filter = null, $from = null, $to = null, $dateRange = null)
    {
        $query = $this->rental_provider_trip_commission_query(TripTransaction::query(), $provider_id, $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id');

        if ($dateRange) {
            $query->whereBetween('trip_transactions.created_at', $dateRange);
        } else {
            $query->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at');
        }

        return $query->selectRaw("
                SUM(
                    trip_transactions.admin_commission
                ) as total_amount,
                COUNT(trip_transactions.id) as total_count
            ")
            ->first();
    }

    private function rental_provider_subscription_query($query, $provider_id = 'all', $module_id = 'all')
    {
        return $query
            ->where('payment_status', 'success')
            ->where('is_trial', 0)
            ->when(!in_array($provider_id, [null, '', 'all'], true), function ($builder) use ($provider_id) {
                $builder->where('store_id', $provider_id);
            })
            ->whereHas('store.module', function ($builder) use ($module_id) {
                if (!in_array($module_id, [null, '', 'all'], true)) {
                    $builder->where('id', $module_id);
                    return;
                }

                $builder->whereIn('id', $this->rental_provider_module_ids());
            });
    }

    private function rental_provider_transaction_type($type): string
    {
        return match ($type) {
            'earning', 'order', null, '' => 'order',
            'expense' => 'expense',
            'subscription' => 'subscription',
            default => 'order',
        };
    }


    private function rental_provider_trip_adjusted_commission_sql(): string
    {
        return "
            (
                trips.trip_amount
                + trips.coupon_discount_amount
                + trips.ref_bonus_amount
                + trips.discount_on_trip
                + COALESCE((SELECT SUM(opd.amount_saved) FROM order_pro_discounts opd WHERE opd.trip_id = trips.id), 0)
                - trips.additional_charge
                - trips.tax_amount
            )
        ";
    }

    private function rental_admin_trip_earning_amount($adminCommission, $additionalCharge): float
    {
        return round(($adminCommission ?? 0) + ($additionalCharge ?? 0), 2);
    }

    private function rental_provider_format_paginated_transactions($transactions, $limit = null, $offset = null): array
    {
        return [
            'total_size' => $transactions->total(),
            'limit' => (int) ($limit ?? $transactions->perPage()),
            'offset' => (int) ($offset ?? $transactions->currentPage()),
            'data' => $transactions->items(),
        ];
    }

    private function rental_resolve_previous_summary($query, string $dateColumn, ?array $previousPeriodRange, string $aggregateColumn = 'sum', string $select = 'amount')
    {
        if (!$previousPeriodRange) {
            return 0;
        }

        $previousQuery = (clone $query)->whereBetween($dateColumn, $previousPeriodRange);

        if ($aggregateColumn === 'value') {
            return $previousQuery->value($select) ?? 0;
        }

        return $previousQuery->sum($select) ?? 0;
    }

    public function buildRentalAdminEarningSummary($filter, $from, $to, $module_id = 'all')
    {
        $previousPeriodRange = $this->getPreviousPeriodRange($filter, $from, $to);

        $tripCommissionBaseQuery = $this->rental_admin_earning_query(
            TripTransaction::query(),
            $module_id
        );

        $currentTripData = (clone $tripCommissionBaseQuery)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->selectRaw('
                SUM(
                    trip_transactions.admin_commission
                ) as trip_commission,
                SUM(trip_transactions.additional_charge) as additional_charge
            ')
            ->first();

        $previousTripData = $previousPeriodRange
            ? (clone $tripCommissionBaseQuery)
                ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
                ->whereBetween('trip_transactions.created_at', $previousPeriodRange)
                ->selectRaw('
                    SUM(
                        trip_transactions.admin_commission
                    ) as trip_commission,
                    SUM(trip_transactions.additional_charge) as additional_charge
                ')
                ->first()
            : (object) [];

        $trip_commission = round($currentTripData->trip_commission ?? 0, 2);
        $trip_previous_commission = round($previousTripData->trip_commission ?? 0, 2);
        $additional_charge = round($currentTripData->additional_charge ?? 0, 2);
        $previous_additional_charge = round($previousTripData->additional_charge ?? 0, 2);
        $trip_earning = $this->rental_admin_trip_earning_amount($trip_commission, $additional_charge);
        $previous_trip_earning = $this->rental_admin_trip_earning_amount($trip_previous_commission, $previous_additional_charge);

        $expenseBaseQuery = $this->rental_admin_expense_query(Expense::query())
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->whereHas('trip', function ($tripQuery) use ($module_id) {
                    $tripQuery->where('module_id', $module_id);
                });
            });

        $admin_expense = (clone $expenseBaseQuery)
            ->applyDateFilter($filter, $from, $to, 'expenses.created_at')
            ->sum('amount');

        $admin_previous_expense = $this->rental_resolve_previous_summary(
            $expenseBaseQuery,
            'expenses.created_at',
            $previousPeriodRange
        );

        $subscriptionBaseQuery = $this->rental_admin_subscription_query(
            SubscriptionTransaction::query(),
            $module_id
        );

        $subscription_earning = (clone $subscriptionBaseQuery)
            ->applyDateFilter($filter, $from, $to, 'subscription_transactions.created_at')
            ->sum('paid_amount');

        $subscription_previous_earning = $this->rental_resolve_previous_summary(
            $subscriptionBaseQuery,
            'subscription_transactions.created_at',
            $previousPeriodRange,
            'sum',
            'paid_amount'
        );

        $admin_earning = $trip_earning + $subscription_earning;
        $admin_previous_earning = $previous_trip_earning + $subscription_previous_earning;
        $net_profit = $admin_earning - $admin_expense;
        $previous_net_profit = $admin_previous_earning - $admin_previous_expense;

        [$admin_earning_percentage, $admin_earning_positive] =
            $this->calculatePercentageData($admin_earning, $admin_previous_earning);

        [$admin_expense_percentage, $admin_expense_positive] =
            $this->calculatePercentageData($admin_expense, $admin_previous_expense);

        [$net_profit_percentage, $net_profit_positive] =
            $this->calculatePercentageData($net_profit, $previous_net_profit);

        [$subscription_percentage] = $this->calculatePercentage($subscription_earning, $admin_earning);
        [$trip_commission_percentage] = $this->calculatePercentage($trip_commission, $admin_earning);
        [$additional_charge_percentage] = $this->calculatePercentage($additional_charge, $admin_earning);

        return [
            'admin_earning' => $admin_earning,
            'admin_previous_earning' => $admin_previous_earning,
            'admin_earning_positive' => $admin_earning_positive,
            'admin_earning_percentage' => $admin_earning_percentage,
            'admin_expense' => $admin_expense,
            'admin_previous_expense' => $admin_previous_expense,
            'admin_expense_positive' => $admin_expense_positive,
            'admin_expense_percentage' => $admin_expense_percentage,
            'net_profit' => $net_profit,
            'previous_net_profit' => $previous_net_profit,
            'net_profit_positive' => $net_profit_positive,
            'net_profit_percentage' => $net_profit_percentage,
            'subscription_earning' => $subscription_earning,
            'subscription_previous_earning' => $subscription_previous_earning,
            'subscription_percentage' => $subscription_percentage,
            'trip_commission' => $trip_commission,
            'trip_previous_commission' => $trip_previous_commission,
            'trip_commission_percentage' => $trip_commission_percentage,
            'additional_charge' => $additional_charge,
            'previous_additional_charge' => $previous_additional_charge,
            'additional_charge_percentage' => $additional_charge_percentage,
        ];
    }

    public function buildRentalAdminEarningBreakdown($filter, $from, $to, $module_id = 'all')
    {
        $summary = $this->buildRentalAdminEarningSummary($filter, $from, $to, $module_id);

        return [
            'subscription_earning' => round($summary['subscription_earning'], config('round_up_to_digit')),
            'subscription_percentage' => $summary['subscription_percentage'],
            'trip_commission' => round($summary['trip_commission'], config('round_up_to_digit')),
            'trip_commission_percentage' => $summary['trip_commission_percentage'],
            'additional_charge' => round($summary['additional_charge'], config('round_up_to_digit')),
            'additional_charge_percentage' => $summary['additional_charge_percentage'],
        ];
    }

    public function buildRentalAdminExpenseBreakdown($filter, $from, $to, $module_id = 'all')
    {
        $summary = $this->buildRentalAdminEarningSummary($filter, $from, $to, $module_id);

        $expenseData = $this->rental_admin_expense_query(Expense::query())
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->whereHas('trip', function ($tripQuery) use ($module_id) {
                    $tripQuery->where('module_id', $module_id);
                });
            })
            ->applyDateFilter($filter, $from, $to, 'expenses.created_at')
            ->selectRaw("
                SUM(CASE WHEN expenses.type = 'CashBack' THEN expenses.amount ELSE 0 END) as cashback,
                SUM(CASE WHEN expenses.type = 'discount_on_trip' THEN expenses.amount ELSE 0 END) as discount_on_trip,
                SUM(CASE WHEN expenses.type = 'coupon_discount' THEN expenses.amount ELSE 0 END) as coupon_discount,
                SUM(CASE WHEN expenses.type = 'pro_discount' THEN expenses.amount ELSE 0 END) as pro_discount
            ")
            ->first();

        [$cashback_percentage] = $this->calculatePercentage($expenseData->cashback, $summary['admin_expense']);
        [$discount_on_trip_percentage] = $this->calculatePercentage($expenseData->discount_on_trip, $summary['admin_expense']);
        [$coupon_discount_percentage] = $this->calculatePercentage($expenseData->coupon_discount, $summary['admin_expense']);
        [$pro_discount_percentage] = $this->calculatePercentage($expenseData->pro_discount, $summary['admin_expense']);

        return [
            'cashback' => round($expenseData->cashback, config('round_up_to_digit')),
            'cashback_percentage' => $cashback_percentage,
            'discount_on_trip' => round($expenseData->discount_on_trip, config('round_up_to_digit')),
            'discount_on_trip_percentage' => $discount_on_trip_percentage,
            'coupon_discount' => round($expenseData->coupon_discount, config('round_up_to_digit')),
            'coupon_discount_percentage' => $coupon_discount_percentage,
            'pro_discount' => round($expenseData->pro_discount, config('round_up_to_digit')),
            'pro_discount_percentage' => $pro_discount_percentage,
        ];
    }

    public function getRentalAdminEarningTransactions($request, $filter, $from, $to, $nopaginate = false, $module_id = 'all')
    {
        $search = $request->query('search');

        $query = $this->rental_admin_earning_query(
            TripTransaction::with(['trip.provider']),
            $module_id
        )
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TRP', '#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('trip_transactions.id', 'like', "%{$cleanSearch}%")
                        ->orWhere('trip_transactions.trip_id', 'like', "%{$cleanSearch}%")
                        ->orWhereHas('trip.provider', function ($providerQuery) use ($search) {
                            $providerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->select('trip_transactions.*', 'trips.discount_on_trip', 'trips.discount_on_trip_by')
            ->latest('trip_transactions.created_at');

        $results = $nopaginate
            ? $query->get()
            : $query->paginate(config('default_pagination', 25))->withQueryString();

        $collection = ($nopaginate ? $results : $results->getCollection())->map(function ($transaction) {
            $admin_discount = 0;
            if ($transaction->discount_on_trip > 0 && $transaction->discount_on_trip_by === 'vendor' && $transaction->is_subscribed == 0) {
                $admin_discount = ($transaction->discount_on_trip / 100) * $transaction->commission_percentage;
            }
            $amount = $this->rental_admin_trip_earning_amount(
                ($transaction->admin_commission),
                $transaction->additional_charge
            );

            return [
                'transaction_id' => '#TXN ' . $transaction->id,
                'date' => $transaction->created_at,
                'source' => $transaction->trip?->provider?->name ?? translate('messages.Provider'),
                'source_type' => 'Provider',
                'earning_from_badge' => 'Trip Earning',
                'earning_from' => '#TRP ' . $transaction->trip_id,
                'trip_id' => $transaction->trip_id,
                'amount' => $amount,
                'breakdown' => [
                    'trip_commission' => round(($transaction->admin_commission) ?? 0, 2),
                    'additional_charge' => round($transaction->additional_charge ?? 0, 2),
                ],
            ];
        });

        if ($nopaginate) {
            return $collection;
        }

        $results->setCollection($collection);
        return $results;
    }

    public function getRentalAdminExpenseTransactions($request, $filter, $from, $to, $nopaginate = false, $module_id = 'all')
    {
        $search = $request->query('search');

        $query = $this->rental_admin_expense_query(
            Expense::with(['trip.provider'])
        )
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->whereHas('trip', function ($tripQuery) use ($module_id) {
                    $tripQuery->where('module_id', $module_id);
                });
            })
            ->applyDateFilter($filter, $from, $to, 'expenses.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TRP', '#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('id', 'like', "%{$cleanSearch}%")
                        ->orWhere('trip_id', 'like', "%{$cleanSearch}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('trip.provider', function ($providerQuery) use ($search) {
                            $providerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('expenses.created_at');

        $results = $nopaginate
            ? $query->get()
            : $query->paginate(config('default_pagination', 25))->withQueryString();

        $collection = ($nopaginate ? $results : $results->getCollection())->map(function ($expense) {
            $source = $expense->trip?->provider?->name ?? 'Admin';
            $sourceType = $expense->trip?->provider ? 'Provider' : 'Admin';

            if ($expense->type === 'tax') {
                $source = 'Government';
                $sourceType = 'Tax Office';
            }

            return [
                'transaction_id' => '#TXN ' . $expense->id,
                'date' => $expense->created_at,
                'source' => $source,
                'source_type' => $sourceType,
                'expense_source_badge' => ucwords(str_replace('_', ' ', $expense->type)),
                'expense_source' => '#TRP ' . $expense->trip_id,
                'trip_id' => $expense->trip_id,
                'amount' => $expense->amount,
                'breakdown' => [],
            ];
        });

        if ($nopaginate) {
            return $collection;
        }

        $results->setCollection($collection);
        return $results;
    }

    public function getRentalAdminSubscriptionTransactions($request, $filter, $from, $to, $nopaginate = false, $module_id = 'all')
    {
        $search = $request->query('search');

        $query = $this->rental_admin_subscription_query(
            SubscriptionTransaction::with('store'),
            $module_id
        )
            ->applyDateFilter($filter, $from, $to, 'subscription_transactions.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('id', 'like', "%{$cleanSearch}%")
                        ->orWhere('plan_type', 'like', "%{$search}%")
                        ->orWhereHas('store', function ($storeQuery) use ($search) {
                            $storeQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('subscription_transactions.created_at');

        $results = $nopaginate
            ? $query->get()
            : $query->paginate(config('default_pagination', 25))->withQueryString();

        $collection = ($nopaginate ? $results : $results->getCollection())->map(function ($transaction) {
            $type = match ($transaction->plan_type) {
                'renew' => 'Renew Subscription',
                'new_plan' => 'Migrate to New Plan',
                'first_purchased' => 'First Purchased',
                'free_trial' => 'Free Trial',
                default => ucwords(str_replace('_', ' ', $transaction->plan_type)),
            };

            $typeBadgeClass = match ($transaction->plan_type) {
                'renew' => 'bg-secondary text-dark',
                'new_plan' => 'bg-warning text-dark',
                'first_purchased' => 'bg-success text-white',
                'free_trial' => 'bg-info text-white',
                default => 'bg-light text-dark',
            };

            return [
                'transaction_id' => '#TXN ' . $transaction->id,
                'date' => $transaction->created_at,
                'source' => $transaction->store?->name ?? translate('messages.Provider'),
                'source_type' => 'Provider',
                'transaction_type' => $type,
                'transaction_type_badge_class' => $typeBadgeClass,
                'amount' => $transaction->paid_amount,
            ];
        });

        if ($nopaginate) {
            return $collection;
        }

        $results->setCollection($collection);
        return $results;
    }

    public function getRentalAdminTrendData($filter, $from, $to, $module_id = 'all')
    {
        $today = Carbon::now();
        $months = collect();
        $dateFormat = ($filter === 'this_week' || $filter === 'this_month') ? '%Y-%m-%d' : '%Y-%m';
        $singleDayCustom = $filter === 'custom' && $from && $to && $from === $to;

        if ($filter === 'this_year') {
            $startMonth = Carbon::now()->startOfYear();
            for ($i = 0; $i <= $today->month - 1; $i++) {
                $months->push($startMonth->copy()->addMonths($i)->format('Y-m'));
            }
        } elseif ($filter === 'this_month') {
            $daysInMonth = Carbon::now()->daysInMonth;
            $startOfMonth = Carbon::now()->startOfMonth();
            for ($i = 0; $i < $daysInMonth; $i++) {
                $months->push($startOfMonth->copy()->addDays($i)->format('Y-m-d'));
            }
        } elseif ($filter === 'this_week') {
            $startOfWeek = Carbon::now()->startOfWeek();
            for ($i = 0; $i <= 6; $i++) {
                $months->push($startOfWeek->copy()->addDays($i)->format('Y-m-d'));
            }
        } elseif ($filter === 'previous_year') {
            $startMonth = Carbon::now()->subYear()->startOfYear();
            for ($i = 0; $i < 12; $i++) {
                $months->push($startMonth->copy()->addMonths($i)->format('Y-m'));
            }
        } elseif ($filter === 'custom' && $from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
            $diffDays = $start->diffInDays($end);

            if ($diffDays > 365) {
                $dateFormat = '%Y';
                $temp = $start->copy()->startOfYear();
                while ($temp->year <= $end->year) {
                    $months->push($temp->format('Y'));
                    $temp->addYear();
                }
            } elseif ($diffDays > 31) {
                $dateFormat = '%Y-%m';
                $temp = $start->copy()->startOfMonth();
                while ($temp->format('Y-m') <= $end->format('Y-m')) {
                    $months->push($temp->format('Y-m'));
                    $temp->addMonth();
                }
            } else {
                $dateFormat = '%Y-%m-%d';
                $temp = $start->copy();
                while ($temp->lte($end)) {
                    $months->push($temp->format('Y-m-d'));
                    $temp->addDay();
                }
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $months->push($today->copy()->subMonths($i)->format('Y-m'));
            }
        }

        $earnings = $this->rental_admin_earning_query(TripTransaction::query(), $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->selectRaw("DATE_FORMAT(trip_transactions.created_at, '$dateFormat') as month")
            ->selectRaw("SUM(
                    trip_transactions.admin_commission  + trip_transactions.additional_charge
                ) as total_earning")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_earning', 'month');

        $subscriptions = $this->rental_admin_subscription_query(SubscriptionTransaction::query(), $module_id)
            ->applyDateFilter($filter, $from, $to, 'subscription_transactions.created_at')
            ->selectRaw("DATE_FORMAT(subscription_transactions.created_at, '$dateFormat') as month")
            ->selectRaw("SUM(paid_amount) as total_subscription_earning")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_subscription_earning', 'month');

        $expenses = $this->rental_admin_expense_query(Expense::query())
            ->when(!in_array($module_id, [null, '', 'all'], true), function ($builder) use ($module_id) {
                $builder->whereHas('trip', function ($tripQuery) use ($module_id) {
                    $tripQuery->where('module_id', $module_id);
                });
            })
            ->applyDateFilter($filter, $from, $to, 'expenses.created_at')
            ->selectRaw("DATE_FORMAT(expenses.created_at, '$dateFormat') as month")
            ->selectRaw("SUM(amount) as total_expense")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_expense', 'month');

        return [
            'categories' => $months->map(function ($month) use ($filter, $dateFormat, $singleDayCustom) {
                if ($filter === 'this_week') {
                    return Carbon::parse($month)->format('D');
                }
                if ($filter === 'this_month') {
                    return Carbon::parse($month)->format('j');
                }
                if ($filter === 'custom') {
                    if ($singleDayCustom) {
                        return Carbon::parse($month)->format('d M Y');
                    }
                    if ($dateFormat === '%Y') {
                        return $month;
                    }
                    if ($dateFormat === '%Y-%m') {
                        return Carbon::parse($month . '-01')->format('M');
                    }
                    if ($dateFormat === '%Y-%m-%d') {
                        return Carbon::parse($month)->format('j');
                    }
                }

                return Carbon::parse($month . '-01')->format('M');
            }),
            'earning_series' => $months->map(function ($month) use ($earnings, $subscriptions) {
                return round(($earnings[$month] ?? 0) + ($subscriptions[$month] ?? 0), 2);
            }),
            'expense_series' => $months->map(function ($month) use ($expenses) {
                return round($expenses[$month] ?? 0, 2);
            }),
        ];
    }

    public function getRentalAdminTopEarningProviders($filter, $from, $to, $module_id = 'all')
    {
        $moduleIds = !in_array($module_id, [null, '', 'all'], true)
            ? [(int) $module_id]
            : $this->rental_provider_module_ids()->all();

        $subscriptionQuery = DB::table('subscription_transactions as subscription_transactions')
            ->join('stores', 'stores.id', '=', 'subscription_transactions.store_id')
            ->select('subscription_transactions.store_id')
            ->selectRaw('COALESCE(SUM(subscription_transactions.paid_amount), 0) as subscription_earning')
            ->where('subscription_transactions.payment_status', 'success')
            ->where('subscription_transactions.is_trial', 0)
            ->whereIn('stores.module_id', $moduleIds)
            ->when($filter === 'custom' && $from && $to, function ($query) use ($from, $to) {
                $query->whereBetween('subscription_transactions.created_at', ["$from 00:00:00", "$to 23:59:59"]);
            }, function ($query) use ($filter) {
                if ($filter === 'this_year') {
                    $query->whereYear('subscription_transactions.created_at', now()->year);
                } elseif ($filter === 'this_month') {
                    $query->whereYear('subscription_transactions.created_at', now()->year)
                        ->whereMonth('subscription_transactions.created_at', now()->month);
                } elseif ($filter === 'previous_year') {
                    $query->whereYear('subscription_transactions.created_at', now()->year - 1);
                } elseif ($filter === 'this_week') {
                    $query->whereBetween('subscription_transactions.created_at', [
                        now()->startOfWeek()->format('Y-m-d H:i:s'),
                        now()->endOfWeek()->format('Y-m-d H:i:s'),
                    ]);
                }
            })
            ->groupBy('subscription_transactions.store_id');

        return DB::table('stores')
            ->whereIn('stores.module_id', $moduleIds)
            ->leftJoin('trip_transactions', function ($join) use ($filter, $from, $to) {
                $join->on('stores.id', '=', 'trip_transactions.provider_id');

                if ($filter === 'custom' && $from && $to) {
                    $join->whereBetween('trip_transactions.created_at', ["$from 00:00:00", "$to 23:59:59"]);
                } elseif ($filter === 'this_year') {
                    $join->whereYear('trip_transactions.created_at', now()->year);
                } elseif ($filter === 'this_month') {
                    $join->whereYear('trip_transactions.created_at', now()->year)
                        ->whereMonth('trip_transactions.created_at', now()->month);
                } elseif ($filter === 'previous_year') {
                    $join->whereYear('trip_transactions.created_at', now()->year - 1);
                } elseif ($filter === 'this_week') {
                    $join->whereBetween('trip_transactions.created_at', [
                        now()->startOfWeek()->format('Y-m-d H:i:s'),
                        now()->endOfWeek()->format('Y-m-d H:i:s'),
                    ]);
                }
            })
            ->leftJoinSub($subscriptionQuery, 'subscription_earnings', 'stores.id', '=', 'subscription_earnings.store_id')
            ->leftJoin('zones', 'stores.zone_id', '=', 'zones.id')
            ->select(
                'stores.id',
                'stores.logo',
                'stores.name as provider_name',
                'zones.name as zone_name'
            )
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->selectRaw('COALESCE(SUM(
                trip_transactions.admin_commission + trip_transactions.additional_charge
            ), 0) as trip_earning')
            ->selectRaw('COALESCE(subscription_earnings.subscription_earning, 0) as subscription_earning')
            ->selectRaw('COALESCE(SUM(
                trip_transactions.admin_commission + trip_transactions.additional_charge
            ), 0) + COALESCE(subscription_earnings.subscription_earning, 0) as total_earning')
            ->selectRaw('COUNT(trip_transactions.id) as total_transactions')
            ->selectSub(function ($query) {
                $query->from('storages as storage')
                    ->whereColumn('storage.data_id', 'stores.id')
                    ->where('storage.data_type', Store::class)
                    ->limit(1)
                    ->select('value');
            }, 'storage')
            ->groupBy('stores.id', 'stores.logo', 'stores.name', 'zones.name', 'subscription_earnings.subscription_earning')
            ->havingRaw('total_earning > 0')
            ->orderByDesc('total_earning')
            ->limit(10)
            ->get();
    }

    public function getRentalAdminZoneWiseEarnings($filter, $from, $to, $module_id = 'all')
    {
        $moduleIds = !in_array($module_id, [null, '', 'all'], true)
            ? [(int) $module_id]
            : $this->rental_provider_module_ids()->all();

        $tripEarningsPerZone = DB::table('trip_transactions')
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->select('trip_transactions.zone_id')
            ->selectRaw('COALESCE(SUM(
                trip_transactions.admin_commission  + trip_transactions.additional_charge
            ), 0) as trip_earning')
            ->whereIn('trip_transactions.module_id', $moduleIds)
            ->when($filter === 'custom' && $from && $to, function ($query) use ($from, $to) {
                $query->whereBetween('trip_transactions.created_at', ["$from 00:00:00", "$to 23:59:59"]);
            }, function ($query) use ($filter) {
                if ($filter === 'this_year') {
                    $query->whereYear('trip_transactions.created_at', now()->year);
                } elseif ($filter === 'this_month') {
                    $query->whereYear('trip_transactions.created_at', now()->year)
                        ->whereMonth('trip_transactions.created_at', now()->month);
                } elseif ($filter === 'previous_year') {
                    $query->whereYear('trip_transactions.created_at', now()->year - 1);
                } elseif ($filter === 'this_week') {
                    $query->whereBetween('trip_transactions.created_at', [
                        now()->startOfWeek()->format('Y-m-d H:i:s'),
                        now()->endOfWeek()->format('Y-m-d H:i:s'),
                    ]);
                }
            })
            ->groupBy('trip_transactions.zone_id');

        $subscriptionEarningsPerZone = DB::table('subscription_transactions')
            ->join('stores', 'stores.id', '=', 'subscription_transactions.store_id')
            ->select('stores.zone_id')
            ->selectRaw('COALESCE(SUM(subscription_transactions.paid_amount), 0) as subscription_earning')
            ->where('subscription_transactions.payment_status', 'success')
            ->where('subscription_transactions.is_trial', 0)
            ->whereIn('stores.module_id', $moduleIds)
            ->when($filter === 'custom' && $from && $to, function ($query) use ($from, $to) {
                $query->whereBetween('subscription_transactions.created_at', ["$from 00:00:00", "$to 23:59:59"]);
            }, function ($query) use ($filter) {
                if ($filter === 'this_year') {
                    $query->whereYear('subscription_transactions.created_at', now()->year);
                } elseif ($filter === 'this_month') {
                    $query->whereYear('subscription_transactions.created_at', now()->year)
                        ->whereMonth('subscription_transactions.created_at', now()->month);
                } elseif ($filter === 'previous_year') {
                    $query->whereYear('subscription_transactions.created_at', now()->year - 1);
                } elseif ($filter === 'this_week') {
                    $query->whereBetween('subscription_transactions.created_at', [
                        now()->startOfWeek()->format('Y-m-d H:i:s'),
                        now()->endOfWeek()->format('Y-m-d H:i:s'),
                    ]);
                }
            })
            ->groupBy('stores.zone_id');

        $topZones = Zone::query()
            ->leftJoinSub($tripEarningsPerZone, 'trip_earnings', 'zones.id', '=', 'trip_earnings.zone_id')
            ->leftJoinSub($subscriptionEarningsPerZone, 'subscription_earnings', 'zones.id', '=', 'subscription_earnings.zone_id')
            ->select('zones.name as zone_name')
            ->selectRaw('COALESCE(trip_earnings.trip_earning, 0) + COALESCE(subscription_earnings.subscription_earning, 0) as total_earning')
            ->selectRaw('(SELECT COUNT(DISTINCT id) FROM stores WHERE zone_id = zones.id AND module_id ' . (count($moduleIds) === 1 ? '= ' . (int) $moduleIds[0] : 'IN (' . implode(',', array_map('intval', $moduleIds)) . ')') . ') as total_stores')
            ->havingRaw('total_earning > 0')
            ->orderByDesc('total_earning')
            ->limit(10)
            ->get();

        $totalZoneEarnings = Zone::query()
            ->leftJoinSub($tripEarningsPerZone, 'trip_earnings', 'zones.id', '=', 'trip_earnings.zone_id')
            ->leftJoinSub($subscriptionEarningsPerZone, 'subscription_earnings', 'zones.id', '=', 'subscription_earnings.zone_id')
            ->selectRaw('COALESCE(SUM(trip_earnings.trip_earning), 0) + COALESCE(SUM(subscription_earnings.subscription_earning), 0) as grand_total')
            ->value('grand_total') ?? 0;

        return $topZones->map(function ($zone) use ($totalZoneEarnings) {
            return [
                'zone_name' => $zone->zone_name,
                'total_stores' => (int) ($zone->total_stores ?? 0),
                'total_earning' => round($zone->total_earning ?? 0, config('round_up_to_digit')),
                'percentage_of_earning' => $totalZoneEarnings > 0
                    ? round((($zone->total_earning ?? 0) / $totalZoneEarnings) * 100, 2)
                    : 0,
            ];
        });
    }

    public function buildRentalProviderSummary($provider_id, $filter, $from, $to, $module_id = 'all')
    {
        $previousPeriodRange = $this->getPreviousPeriodRange($filter, $from, $to);

        $adjustedCommissionSql = $this->rental_provider_trip_adjusted_commission_sql();

        $tripBaseQuery = $this->rental_provider_earning_query(TripTransaction::query(), $provider_id, $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id');

        $currentTripData = (clone $tripBaseQuery)
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->selectRaw("
                SUM(
                    ($adjustedCommissionSql)
                ) as total_solid_earning,

                 SUM(trip_transactions.admin_commission) as admin_commission,
                SUM(trip_transactions.tax) as tax_amount,
                COUNT(trip_transactions.id) as total_trip_transactions
            ")
            ->first();

        $previousTripData = $previousPeriodRange
            ? (clone $tripBaseQuery)
                ->whereBetween('trip_transactions.created_at', $previousPeriodRange)
                ->selectRaw("
                    SUM(
                        ($adjustedCommissionSql)
                    ) as total_solid_earning,
                     SUM(trip_transactions.admin_commission) as admin_commission,
                    SUM(trip_transactions.tax) as tax_amount
                ")
                ->first()
            : (object) [];

        $currentVendorExpense = $this->rental_provider_expense_totals($provider_id, $module_id, $filter, $from, $to);
        $currentTripCommission = $this->rental_provider_trip_commission_totals($provider_id, $module_id, $filter, $from, $to);

        $previousVendorExpense = $previousPeriodRange
            ? $this->rental_provider_expense_totals($provider_id, $module_id, null, null, null, $previousPeriodRange)
            : (object) [];
        $previousTripCommission = $previousPeriodRange
            ? $this->rental_provider_trip_commission_totals($provider_id, $module_id, null, null, null, $previousPeriodRange)
            : (object) [];

        $subscriptionBaseQuery = $this->rental_provider_subscription_query(SubscriptionTransaction::query(), $provider_id, $module_id);
        $currentSubscription = (clone $subscriptionBaseQuery)
            ->applyDateFilter($filter, $from, $to, 'subscription_transactions.created_at')
            ->selectRaw("SUM(paid_amount) as total_amount, COUNT(id) as total_count")
            ->first();

        $previousSubscription = $previousPeriodRange
            ? (clone $subscriptionBaseQuery)
                ->whereBetween('subscription_transactions.created_at', $previousPeriodRange)
                ->selectRaw("SUM(paid_amount) as total_amount")
                ->first()
            : (object) [];

        $tripStoreEarning = round(max(
            0,
            ($currentTripData->total_solid_earning ?? 0) - ($currentTripData->admin_commission ?? 0)
        ), 2);

       
        $taxAmount = round($currentTripData->tax_amount ?? 0, 2);

        $vendorExpenseAmount = round($currentVendorExpense->total_amount ?? 0, 2);
        $previousVendorExpenseAmount = round($previousVendorExpense->total_amount ?? 0, 2);
        $tripCommissionAmount = round($currentTripCommission->total_amount ?? 0, 2);
        $previousTripCommissionAmount = round($previousTripCommission->total_amount ?? 0, 2);
        $discountOnTripAmount = round($currentVendorExpense->discount_on_trip ?? 0, 2);
        $couponDiscountAmount = round($currentVendorExpense->coupon_discount ?? 0, 2);
        $subscriptionAmount = round($currentSubscription->total_amount ?? 0, 2);
        $previousSubscriptionAmount = round($previousSubscription->total_amount ?? 0, 2);

        $totalEarning = round($currentTripData->total_solid_earning ?? 0, 2) + round($currentTripData->tax_amount ?? 0, 2);
        $previousTotalEarning = round($previousTripData->total_solid_earning ?? 0, 2) + round($previousTripData->tax_amount ?? 0, 2);

        $totalExpense = $subscriptionAmount + $vendorExpenseAmount + $tripCommissionAmount;
        $previousTotalExpense = $previousSubscriptionAmount + $previousVendorExpenseAmount + $previousTripCommissionAmount;

        $netIncome = $totalEarning - $totalExpense;
        $previousNetIncome = $previousTotalEarning - $previousTotalExpense;

        [$totalEarningPercentage, $totalEarningPositive] =
            $this->calculatePercentageData($totalEarning, $previousTotalEarning);
        [$totalExpensePercentage, $totalExpensePositive] =
            $this->calculatePercentageData($totalExpense, $previousTotalExpense);
        [$netIncomePercentage, $netIncomePositive] =
            $this->calculatePercentageData($netIncome, $previousNetIncome);

        $earningBreakdownBase = max($totalEarning - $currentTripData->admin_commission, 0);

        [$tripStoreEarningPercentage] = $this->calculatePercentage($tripStoreEarning, $earningBreakdownBase);
        [$taxAmountPercentage] = $this->calculatePercentage($taxAmount, $earningBreakdownBase);

        
        [$tripCommissionPercentage] = $this->calculatePercentage($tripCommissionAmount, $totalExpense);
        [$vendorExpenseAmountPercentage] = $this->calculatePercentage($vendorExpenseAmount, $totalExpense);
        [$discountOnTripPercentage] = $this->calculatePercentage($discountOnTripAmount, $totalExpense);
        [$couponDiscountPercentage] = $this->calculatePercentage($couponDiscountAmount, $totalExpense);
        [$subscriptionExpensePercentage] = $this->calculatePercentage($subscriptionAmount, $totalExpense);

        return [
            'total_earning' => round($totalEarning, config('round_up_to_digit')),
            'previous_total_earning' => round($previousTotalEarning, config('round_up_to_digit')),
            'total_earning_percentage' => $totalEarningPercentage,
            'total_earning_positive' => $totalEarningPositive,
            'total_expense' => round($totalExpense, config('round_up_to_digit')),
            'previous_total_expense' => round($previousTotalExpense, config('round_up_to_digit')),
            'total_expense_percentage' => $totalExpensePercentage,
            'total_expense_positive' => $totalExpensePositive,
            'net_income' => round($netIncome, config('round_up_to_digit')),
            'previous_net_income' => round($previousNetIncome, config('round_up_to_digit')),
            'net_income_percentage' => $netIncomePercentage,
            'net_income_positive' => $netIncomePositive,
            'counts' => [
                'earning' => (int) ($currentTripData->total_trip_transactions ?? 0),
                'expense' => (int) (($currentVendorExpense->total_count ?? 0) + ($currentTripCommission->total_count ?? 0)),
                'subscription' => (int) ($currentSubscription->total_count ?? 0),
            ],
            'breakdown' => [
                'trip_store_earning' => round($tripStoreEarning, config('round_up_to_digit')),
                'trip_store_earning_percentage' => $tripStoreEarningPercentage,
                'tax_amount' => round($taxAmount, config('round_up_to_digit')),
                'tax_amount_percentage' => $taxAmountPercentage,
            ],
            'trip_commission' => round($tripCommissionAmount, config('round_up_to_digit')),
            'trip_commission_percentage' => $tripCommissionPercentage,
            'vendor_expense_amount' => round($vendorExpenseAmount, config('round_up_to_digit')),
            'vendor_expense_amount_percentage' => $vendorExpenseAmountPercentage,
            'discount_on_trip' => round($discountOnTripAmount, config('round_up_to_digit')),
            'discount_on_trip_percentage' => $discountOnTripPercentage,
            'coupon_discount' => round($couponDiscountAmount, config('round_up_to_digit')),
            'coupon_discount_percentage' => $couponDiscountPercentage,
            'subscription_amount' => round($subscriptionAmount, config('round_up_to_digit')),
            'subscription_amount_percentage' => $subscriptionExpensePercentage,
        ];
    }

    public function buildRentalProviderEarningBreakdown($provider_id, $filter, $from, $to, $module_id = 'all')
    {
        return $this->buildRentalProviderSummary($provider_id, $filter, $from, $to, $module_id)['breakdown'];
    }

    public function buildRentalProviderExpenseBreakdown($provider_id, $filter, $from, $to, $module_id = 'all')
    {
        $summary = $this->buildRentalProviderSummary($provider_id, $filter, $from, $to, $module_id);
        return [
            'trip_commission' => $summary['trip_commission'],
            'trip_commission_percentage' => $summary['trip_commission_percentage'],
            'vendor_expense_amount' => $summary['vendor_expense_amount'],
            'vendor_expense_amount_percentage' => $summary['vendor_expense_amount_percentage'],
            'discount_on_trip' => $summary['discount_on_trip'],
            'discount_on_trip_percentage' => $summary['discount_on_trip_percentage'],
            'coupon_discount' => $summary['coupon_discount'],
            'coupon_discount_percentage' => $summary['coupon_discount_percentage'],
            'subscription_amount' => $summary['subscription_amount'],
            'subscription_amount_percentage' => $summary['subscription_amount_percentage'],
        ];
    }

    public function getRentalProviderTrendData($provider_id, $filter, $from, $to, $module_id = 'all')
    {
        $today = Carbon::now();
        $periods = collect();
        $dateFormat = ($filter === 'this_week' || $filter === 'this_month') ? '%Y-%m-%d' : '%Y-%m';
        $singleDayCustom = $filter === 'custom' && $from && $to && $from === $to;

        if ($filter === 'this_year') {
            $startMonth = Carbon::now()->startOfYear();
            for ($i = 0; $i <= $today->month - 1; $i++) {
                $periods->push($startMonth->copy()->addMonths($i)->format('Y-m'));
            }
        } elseif ($filter === 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            for ($i = 0; $i < Carbon::now()->daysInMonth; $i++) {
                $periods->push($startOfMonth->copy()->addDays($i)->format('Y-m-d'));
            }
        } elseif ($filter === 'this_week') {
            $startOfWeek = Carbon::now()->startOfWeek();
            for ($i = 0; $i < 7; $i++) {
                $periods->push($startOfWeek->copy()->addDays($i)->format('Y-m-d'));
            }
        } elseif ($filter === 'previous_year') {
            $startMonth = Carbon::now()->subYear()->startOfYear();
            for ($i = 0; $i < 12; $i++) {
                $periods->push($startMonth->copy()->addMonths($i)->format('Y-m'));
            }
        } elseif ($filter === 'custom' && $from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
            $diffDays = $start->diffInDays($end);

            if ($diffDays > 365) {
                $dateFormat = '%Y';
                $temp = $start->copy()->startOfYear();
                while ($temp->year <= $end->year) {
                    $periods->push($temp->format('Y'));
                    $temp->addYear();
                }
            } elseif ($diffDays > 31) {
                $dateFormat = '%Y-%m';
                $temp = $start->copy()->startOfMonth();
                while ($temp->format('Y-m') <= $end->format('Y-m')) {
                    $periods->push($temp->format('Y-m'));
                    $temp->addMonth();
                }
            } else {
                $dateFormat = '%Y-%m-%d';
                $temp = $start->copy();
                while ($temp->lte($end)) {
                    $periods->push($temp->format('Y-m-d'));
                    $temp->addDay();
                }
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $periods->push($today->copy()->subMonths($i)->format('Y-m'));
            }
        }

        $earnings = $this->rental_provider_earning_query(TripTransaction::query(), $provider_id, $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->selectRaw("DATE_FORMAT(trip_transactions.created_at, '$dateFormat') as month")
            ->selectRaw("
                SUM(
                   (" . $this->rental_provider_trip_adjusted_commission_sql() . ")
                ) + SUM(trip_transactions.tax) as total_earning
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_earning', 'month');

        $expenses = $this->rental_provider_expense_query(Expense::query(), $provider_id, $module_id)
            ->applyDateFilter($filter, $from, $to, 'expenses.created_at')
            ->selectRaw("DATE_FORMAT(expenses.created_at, '$dateFormat') as month")
            ->selectRaw("SUM(amount) as total_expense")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_expense', 'month');

        $tripCommissions = $this->rental_provider_trip_commission_query(TripTransaction::query(), $provider_id, $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->selectRaw("DATE_FORMAT(trip_transactions.created_at, '$dateFormat') as month")
            ->selectRaw("SUM(
                    trip_transactions.admin_commission 
                ) as total_trip_commission")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_trip_commission', 'month');

        $subscriptions = $this->rental_provider_subscription_query(SubscriptionTransaction::query(), $provider_id, $module_id)
            ->applyDateFilter($filter, $from, $to, 'subscription_transactions.created_at')
            ->selectRaw("DATE_FORMAT(subscription_transactions.created_at, '$dateFormat') as month")
            ->selectRaw("SUM(paid_amount) as total_subscription")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_subscription', 'month');

        return [
            'categories' => $periods->map(function ($period) use ($filter, $dateFormat, $singleDayCustom) {
                if ($filter === 'this_week') {
                    return Carbon::parse($period)->format('D');
                }
                if ($filter === 'this_month') {
                    return Carbon::parse($period)->format('j');
                }
                if ($filter === 'custom') {
                    if ($singleDayCustom) {
                        return Carbon::parse($period)->format('d M Y');
                    }
                    if ($dateFormat === '%Y') {
                        return $period;
                    }
                    if ($dateFormat === '%Y-%m') {
                        return Carbon::parse($period . '-01')->format('M');
                    }
                    if ($dateFormat === '%Y-%m-%d') {
                        return Carbon::parse($period)->format('j');
                    }
                }

                return Carbon::parse($period . '-01')->format('M');
            }),
            'earning_series' => $periods->map(function ($period) use ($earnings) {
                return round($earnings[$period] ?? 0, 2);
            }),
            'expense_series' => $periods->map(function ($period) use ($expenses, $subscriptions, $tripCommissions) {
                return round(($expenses[$period] ?? 0) + ($subscriptions[$period] ?? 0) + ($tripCommissions[$period] ?? 0), 2);
            }),
        ];
    }

    public function getRentalProviderEarningTransactions($request, $provider_id, $filter, $from, $to, $nopaginate = false, $limit = null, $offset = null, $module_id = 'all')
    {
        $search = $request->query('search');

        $query = $this->rental_provider_earning_query(TripTransaction::with(['provider', 'trip', 'trip.proDiscount']), $provider_id, $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TRP', '#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('trip_transactions.id', 'like', "%{$cleanSearch}%")
                        ->orWhere('trip_transactions.trip_id', 'like', "%{$cleanSearch}%")
                        ->orWhereHas('provider', function ($providerQuery) use ($search) {
                            $providerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->select('trip_transactions.*', 'trips.discount_on_trip', 'trips.discount_on_trip_by')
            ->latest('trip_transactions.created_at');

        if ($nopaginate) {
            $results = $query->get();
        } else {
            $perPage = $limit ?? config('default_pagination', 25);
            $page = $offset;
            $results = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        }

        $collection = ($nopaginate ? $results : $results->getCollection())->map(function ($transaction) {
            $totalAmount =  $this->totalStoreAmount($transaction);
            $storeAmountWithoutTax =  $this->getproviderComission($transaction);
            return [
                'transaction_id' => '#TXN ' . $transaction->id,
                'date' => $transaction->created_at,
                'source' => $transaction->provider?->name ?? translate('messages.Provider'),
                'source_type' => 'Provider',
                'earning_from_badge' => 'Trip Earning',
                'earning_from' => '#TRP ' . $transaction->trip_id,
                'trip_id' => $transaction->trip_id,
                'amount' => $totalAmount,
                'breakdown' => [
                    'store_amount_without_tax' => $storeAmountWithoutTax,
                    'tax_amount' => round($transaction->tax ?? 0, 2),
                ],
            ];
        });

        if ($nopaginate) {
            return $collection;
        }

        $results->setCollection($collection);
        return $results;
    }

    private function totalStoreAmount($transaction){
        return $this->getproviderComission($transaction) + ($transaction->tax ?? 0);
    }
    private function getproviderComission($transaction)
    {
            $trip= $transaction->trip;

            $total_discount = $trip->coupon_discount_amount + $trip->ref_bonus_amount + $trip->discount_on_trip + ($trip->proDiscount?->amount_saved ?? 0);
            $original_trip_amount = $trip->trip_amount + $total_discount - $trip->additional_charge - $trip->tax_amount;

            return $original_trip_amount - $transaction->admin_commission;
    }
    public function getRentalProviderExpenseTransactions($request, $provider_id, $filter, $from, $to, $nopaginate = false, $limit = null, $offset = null, $module_id = 'all')
    {
        $search = $request->query('search');

        $expenseQuery = $this->rental_provider_expense_query(Expense::with(['trip.provider']), $provider_id, $module_id)
            ->applyDateFilter($filter, $from, $to, 'expenses.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TRP', '#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('id', 'like', "%{$cleanSearch}%")
                        ->orWhere('trip_id', 'like', "%{$cleanSearch}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('trip.provider', function ($providerQuery) use ($search) {
                            $providerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('expenses.created_at');

        $tripCommissionQuery = $this->rental_provider_trip_commission_query(TripTransaction::with(['provider', 'trip']), $provider_id, $module_id)
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->applyDateFilter($filter, $from, $to, 'trip_transactions.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TRP', '#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('trip_transactions.id', 'like', "%{$cleanSearch}%")
                        ->orWhere('trip_transactions.trip_id', 'like', "%{$cleanSearch}%")
                        ->orWhereHas('provider', function ($providerQuery) use ($search) {
                            $providerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->select('trip_transactions.*', 'trips.discount_on_trip', 'trips.discount_on_trip_by')
            ->latest('trip_transactions.created_at');

        $expenseCollection = $expenseQuery->get()->map(function ($expense) {
            return [
                'transaction_id' => '#TXN ' . $expense->id,
                'date' => $expense->created_at,
                'source' => $expense->trip?->provider?->name ?? translate('messages.Provider'),
                'source_type' => 'Provider',
                'expense_source_badge' => ucwords(str_replace('_', ' ', $expense->type)),
                'expense_source' => '#TRP ' . $expense->trip_id,
                'trip_id' => $expense->trip_id,
                'amount' => $expense->amount,
                'breakdown' => [],
                '_sort_at' => optional($expense->created_at)->timestamp ?? 0,
            ];
        });

        $tripCommissionCollection = $tripCommissionQuery->get()->map(function ($transaction) {
            $admin_discount = 0;
            if ($transaction->discount_on_trip > 0 && $transaction->discount_on_trip_by === 'vendor' && $transaction->is_subscribed == 0) {
                $admin_discount = ($transaction->discount_on_trip / 100) * $transaction->commission_percentage;
            }
            return [
                'transaction_id' => '#TXN ' . $transaction->id,
                'date' => $transaction->created_at,
                'source' => $transaction->provider?->name ?? translate('messages.Provider'),
                'source_type' => 'Provider',
                'expense_source_badge' => 'Trip Commission',
                'expense_source' => '#TRP ' . $transaction->trip_id,
                'trip_id' => $transaction->trip_id,
                'amount' => round(($transaction->admin_commission) ?? 0, 2),
                'breakdown' => [],
                '_sort_at' => optional($transaction->created_at)->timestamp ?? 0,
            ];
        });

        $collection = $expenseCollection
            ->merge($tripCommissionCollection)
            ->sortByDesc('_sort_at')
            ->values()
            ->map(function ($item) {
                unset($item['_sort_at']);
                return $item;
            });

        if ($nopaginate) {
            return $collection;
        }

        $perPage = $limit ?? config('default_pagination', 25);
        $page = (int) ($offset ?? request()->query('page', 1));
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
            ]
        );
        return $paginator->withQueryString();
    }

    public function getRentalProviderSubscriptionTransactions($request, $provider_id, $filter, $from, $to, $nopaginate = false, $limit = null, $offset = null, $module_id = 'all')
    {
        $search = $request->query('search');

        $query = $this->rental_provider_subscription_query(SubscriptionTransaction::with('store'), $provider_id, $module_id)
            ->applyDateFilter($filter, $from, $to, 'subscription_transactions.created_at')
            ->when($search, function ($builder) use ($search) {
                $cleanSearch = str_replace(['#TXN', '#'], '', $search);

                $builder->where(function ($query) use ($search, $cleanSearch) {
                    $query->where('id', 'like', "%{$cleanSearch}%")
                        ->orWhere('plan_type', 'like', "%{$search}%")
                        ->orWhereHas('store', function ($storeQuery) use ($search) {
                            $storeQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('subscription_transactions.created_at');

        if ($nopaginate) {
            $results = $query->get();
        } else {
            $perPage = $limit ?? config('default_pagination', 25);
            $page = $offset;
            $results = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        }

        $collection = ($nopaginate ? $results : $results->getCollection())->map(function ($transaction) {
            $type = match ($transaction->plan_type) {
                'renew' => 'Renew Subscription',
                'new_plan' => 'Migrate to New Plan',
                'first_purchased' => 'First Purchased',
                'free_trial' => 'Free Trial',
                default => ucwords(str_replace('_', ' ', $transaction->plan_type)),
            };

            $badgeClass = match ($transaction->plan_type) {
                'renew' => 'bg-secondary text-dark',
                'new_plan' => 'bg-warning text-dark',
                'first_purchased' => 'bg-success text-white',
                'free_trial' => 'bg-info text-white',
                default => 'bg-light text-dark',
            };

            return [
                'transaction_id' => '#TXN ' . $transaction->id,
                'date' => $transaction->created_at,
                'source' => $transaction->store?->name ?? translate('messages.Provider'),
                'source_type' => 'Provider',
                'transaction_type' => $type,
                'transaction_type_badge_class' => $badgeClass,
                'amount' => $transaction->paid_amount,
                'breakdown' => [],
            ];
        });

        if ($nopaginate) {
            return $collection;
        }

        $results->setCollection($collection);
        return $results;
    }

    public function buildRentalProviderApiReportPayload($request, $provider_id, $filter, $from, $to, $module_id = 'all', $type = 'order', $limit = null, $offset = null): array
    {
        $type = $this->rental_provider_transaction_type($type);
        $summary = $this->buildRentalProviderSummary($provider_id, $filter, $from, $to, $module_id);
        $trend = $this->getRentalProviderTrendData($provider_id, $filter, $from, $to, $module_id);

        $earningTransactions = $type === 'order'
            ? $this->getRentalProviderEarningTransactions($request, $provider_id, $filter, $from, $to, false, $limit, $offset, $module_id)
            : null;
        $expenseTransactions = $type === 'expense'
            ? $this->getRentalProviderExpenseTransactions($request, $provider_id, $filter, $from, $to, false, $limit, $offset, $module_id)
            : null;
        $subscriptionTransactions = $type === 'subscription'
            ? $this->getRentalProviderSubscriptionTransactions($request, $provider_id, $filter, $from, $to, false, $limit, $offset, $module_id)
            : null;

        return [
            'summary' => $summary,
            'earning_breakdown' => $this->buildRentalProviderEarningBreakdown($provider_id, $filter, $from, $to, $module_id),
            'expense_breakdown' => $this->buildRentalProviderExpenseBreakdown($provider_id, $filter, $from, $to, $module_id),
            'trend' => $trend,
            'recent_transactions' => [
                'active_type' => $type === 'order' ? 'earning' : $type,
                'earning' => $earningTransactions ? $this->rental_provider_format_paginated_transactions($earningTransactions, $limit, $offset) : null,
                'expense' => $expenseTransactions ? $this->rental_provider_format_paginated_transactions($expenseTransactions, $limit, $offset) : null,
                'subscription' => $subscriptionTransactions ? $this->rental_provider_format_paginated_transactions($subscriptionTransactions, $limit, $offset) : null,
            ],
            'total_size' => match ($type) {
                'order' => $earningTransactions?->total() ?? 0,
                'expense' => $expenseTransactions?->total() ?? 0,
                'subscription' => $subscriptionTransactions?->total() ?? 0,
                default => 0,
            },
            'limit' => (int) $limit,
            'offset' => (int) $offset,
        ];
    }
}
