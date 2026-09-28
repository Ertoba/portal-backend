<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;


use App\Exports\AdminTaxReportExport;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Entities\TripTransaction;
use Modules\Rental\Exports\AdminTripTaxReportDetailsExport;
use Modules\TaxModule\Entities\Tax;

class AdminTripTaxReportController extends Controller
{

    public function getTaxReport(Request $request)
    {

        $date_range_type = $request->date_range_type;
        $calculate_tax_on = $request->calculate_tax_on;

        if ($date_range_type == 'this_fiscal_year') {
            $dateRange = now()->startOfYear()->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        } else {
            $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        }

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();


        $taxRates =  $this->getTaxRates($request);

        $tax_on_subscription = $taxRates['tax_on_subscription'];
        $tax_on_service_charge = $taxRates['tax_on_service_charge'];
        $tax_on_order_commission = $taxRates['tax_on_order_commission'];


        $orderTaxData = $this->getOrderTaxes($tax_on_service_charge, $tax_on_order_commission,  $startDate, $endDate);

        // $taxOnSubscriptionData = $this->generateSubscriotionTax($tax_on_subscription, $startDate, $endDate);

        // if ($taxOnSubscriptionData) {

        //     $combinedResults = array_merge(
        //         $orderTaxData,
        //         [
        //             'vendor_subscription' => [
        //                 'total_base_amount' => array_reduce($taxOnSubscriptionData, fn($carry, $item) =>  $item->total_paid_amount, 0),
        //                 'taxes' => array_reduce($taxOnSubscriptionData, function ($carry, $item) {
        //                     $carry[$item->tax_name][] = [
        //                         'tax_rate' => $item->tax_rate,
        //                         'total_tax_amount' => $item->total_tax
        //                     ];
        //                     return $carry;
        //                 }, [])
        //             ]
        //         ]
        //     );
        // } else {
            // $combinedResults = $orderTaxData;
            // }
            $combinedResults = $orderTaxData;
            $totalBase = 0;
        $totalTax = 0;

        foreach ($combinedResults as $category) {
            $totalBase += $category['total_base_amount'];
            foreach ($category['taxes'] as $taxGroup) {
                foreach ($taxGroup as $tax) {
                    $totalTax += $tax['total_tax_amount'];
                }
            }
        }
        $selectedTax = [
            // 'tax_on_subscription' => !isset($request->tax_rate) ?   $tax_on_subscription?->select('id', 'tax_rate', 'name')?->toArray() : [],

            'tax_on_service_charge' => !isset($request->tax_rate) ? $tax_on_service_charge?->select('id', 'tax_rate', 'name')?->toArray() : [],

            'tax_on_order_commission' => !isset($request->tax_rate) ? $tax_on_order_commission?->select('id', 'tax_rate', 'name')?->toArray() : [],
            'tax_rate' => $tax_on_subscription?->select('id', 'tax_rate', 'name')?->toArray() ?? [],
        ];


        return view('rental::admin.report.tax-report.admin-tax-report', compact('date_range_type', 'startDate', 'endDate', 'tax_on_subscription', 'combinedResults', 'totalBase', 'totalTax', 'selectedTax', 'calculate_tax_on'));
    }



    // private function generateSubscriotionTax($tax_on_subscription, $startDate, $endDate)
    // {
    //     if (!count($tax_on_subscription)) {
    //         return [];
    //     }
    //     $finalSql = "
    //         SELECT
    //             tax_name,
    //             tax_rate,
    //             SUM(paid_amount) AS total_paid_amount,
    //             SUM(paid_amount * tax_rate / 100) AS total_tax
    //         FROM (
    //             " . implode(" UNION ALL ", array_fill(0, count($tax_on_subscription), "
    //                 SELECT
    //                     ? AS tax_name,
    //                     ? AS tax_rate,
    //                     paid_amount,
    //                     created_at
    //                 FROM subscription_transactions
    //                 WHERE is_trial = 0
    //                 " . ($startDate && $endDate ? "AND created_at BETWEEN ? AND ?" : "") . "
    //             ")) . "
    //         ) AS tax_data
    //         GROUP BY tax_name, tax_rate
    //     ";

    //     $bindings = [];
    //     foreach ($tax_on_subscription as $tax) {
    //         $bindings = array_merge($bindings, [
    //             $tax->name,
    //             (float)$tax->tax_rate,
    //             ...($startDate && $endDate ? [
    //                 $startDate->format('Y-m-d H:i:s'),
    //                 $endDate->format('Y-m-d H:i:s')
    //             ] : [])
    //         ]);
    //     }
    //     if ($bindings) {

    //         $results = DB::select($finalSql, $bindings);
    //     }
    //     return $results ?? [];
    // }
    private function getTaxRates($request)
    {
        $allTaxIds = collect([
            ...($request->tax_rate ?? []),
            ...($request->tax_on_subscription ?? []),
            ...($request->tax_on_service_charge ?? []),
            ...($request->tax_on_order_commission ?? []),
        ])->unique()->filter()->values();


        $taxRates = Tax::whereIn('id', $allTaxIds)->select('id', 'name', 'tax_rate')->get()->keyBy('id');

        if ($request->calculate_tax_on == 'all_source') {
            $tax_on_subscription = $taxRates->only($request->tax_rate ?? []);
            $tax_on_service_charge = $taxRates->only($request->tax_rate ?? []);
            $tax_on_order_commission = $taxRates->only($request->tax_rate ?? []);
        } else {
            $tax_on_subscription = $taxRates->only($request->tax_on_subscription ?? []);
            $tax_on_service_charge = $taxRates->only($request->tax_on_service_charge ?? []);
            $tax_on_order_commission = $taxRates->only($request->tax_on_order_commission ?? []);
        }


        return [
            'tax_on_subscription' => $tax_on_subscription,
            'tax_on_service_charge' => $tax_on_service_charge,
            'tax_on_order_commission' => $tax_on_order_commission,
        ];
    }


    public function getTaxList(Request $request)
    {
        $data = Tax::where('is_active', 1)
            ->where('name', 'like', '%' . $request->q . '%')
            ->select('id', 'name', 'tax_rate')
            ->limit(8)->get()
            ->map(function ($data) {
                return [
                    'id' => $data->id,
                    'text' => $data->name . ' (' . $data->tax_rate . '%)',
                ];
            });
        if (isset($request->all)) {
            $data[] = (object)['id' => 'all', 'text' => translate('messages.all')];
        }
        return response()->json($data);
    }


    private function getOrderTaxes($tax_on_service_charge, $tax_on_order_commission,  $startDate, $endDate)
    {
        $subqueries = [];


        if (count($tax_on_service_charge)) {
            $subqueries[] = $this->buildTaxSubquery($tax_on_service_charge, 'additional_charge', 'service_charge', $startDate, $endDate);
        }

        if (count($tax_on_order_commission)) {
            $subqueries[] = $this->buildTaxSubquery($tax_on_order_commission, 'admin_commission', 'admin_commission', $startDate, $endDate);
        }

        if (!count($subqueries)) {
            return [];
        }

        $finalSql = " SELECT
            tax_type,
            tax_name,
            tax_rate,
            SUM(base_amount) AS total_base_amount,
            SUM(total_tax_amount) AS total_tax_amount
        FROM (  " . implode(" UNION ALL ", $subqueries) . " ) AS combined_tax_data
        GROUP BY tax_type, tax_name, tax_rate
        ORDER BY tax_type, tax_name  ";

        $bindings = [];
        foreach (
            [
                [$tax_on_service_charge, 'additional_charge'],
                [$tax_on_order_commission, 'admin_commission']
            ] as [$taxes, $taxType]
        ) {
            foreach ($taxes as $tax) {
                $bindings = array_merge($bindings, [
                    $tax->name,
                    (float)$tax->tax_rate,
                    $taxType,
                    (float)$tax->tax_rate
                ]);

                if ($startDate && $endDate) {
                    $bindings = array_merge($bindings, [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        if ($bindings) {
            $orderData = collect(DB::select($finalSql, $bindings))
                ->groupBy(['tax_type', 'tax_name'])
                ->map(function ($typeGroup) {
                    $baseAmount = $typeGroup->first()->first()->total_base_amount;
                    return [
                        'total_base_amount' => $baseAmount,
                        'taxes' => $typeGroup->map(function ($nameGroup) {
                            return $nameGroup->map(function ($item) {
                                return [
                                    'tax_rate' => $item->tax_rate,
                                    'total_tax_amount' => $item->total_tax_amount
                                ];
                            });
                        })
                    ];
                })
                ->toArray();
        }
        return $orderData ?? [];
    }


    private function buildTaxSubquery($taxes, $amountColumn, $startDate, $endDate)
    {
        if (count($taxes)) {
            return implode(" UNION ALL ", array_fill(0, count($taxes), " SELECT ? AS tax_name, ? AS tax_rate, ? AS tax_type, {$amountColumn} AS base_amount, ({$amountColumn} * ? / 100) AS total_tax_amount  FROM trip_transactions
            WHERE status IS NULL  " . ($startDate && $endDate ? "AND created_at BETWEEN ? AND ?" : "") . " "));
        }
        return  '';
    }



    public function getTaxDetails(Request $request)
    {

        $date_range_type = $request->date_range_type;
        $calculate_tax_on = $request->calculate_tax_on;

        if ($request->date_range_type == 'this_fiscal_year') {
            $dateRange = now()->startOfYear()->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        } else {
            $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        }

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        $taxRates =  $this->getTaxRates($request);

        $tax_on_subscription = $taxRates['tax_on_subscription'];
        $tax_on_service_charge = $taxRates['tax_on_service_charge'];
        $tax_on_order_commission = $taxRates['tax_on_order_commission'];


        $taxSource = $request->source;
        if ($taxSource == 'vendor_subscription') {
            $data =  $this->getSubsctiprionData($startDate, $endDate, $tax_on_subscription);
            $view = 'rental::admin.report.tax-report.admin-subscription-tax-report-details';
        } elseif ($taxSource == 'admin_commission') {
            $data =  $this->getAdminCommissionTaxData($startDate, $endDate, $tax_on_order_commission);
            $view = 'rental::admin.report.tax-report.admin-tax-report-details';
        }  else {
            $data =  $this->getServiceChargeTaxData($startDate, $endDate, $tax_on_service_charge);
            $view = 'rental::admin.report.tax-report.admin-tax-report-details';
        }

        $total_tax_amount = $data['total_tax_amount'];
        $total_amount = $data['total_amount'];
        $total_count = $data['total_count'];
        $total_tax_rate = $data['total_tax_rate'] ?? 0;
        $total_order_amount = $data['total_order_amount'] ?? 0;
        $taxData = $data['data'];

        $startDate = Carbon::parse($startDate)->format('d M, Y');
        $endDate = Carbon::parse($endDate)->format('d M, Y');

        return view($view, compact(
            'startDate',
            'endDate',
            'date_range_type',
            'calculate_tax_on',
            'total_tax_amount',
            'total_order_amount',
            'total_amount',
            'total_count',
            'total_tax_rate',
            'taxSource',
            'taxData',
        ));
    }


    // private function getSubsctiprionData($startDate, $endDate, $tax_on_subscription, $export = false)
    // {
    //     $summary = DB::selectOne("  SELECT
    //                     COUNT(*) AS total_sub,
    //                     SUM(paid_amount) AS total_sub_amount
    //                 FROM subscription_transactions
    //                 WHERE is_trial = 0
    //                         AND created_at BETWEEN ? AND ?
    //                 LIMIT 1
    //             ", [
    //         $startDate->format('Y-m-d H:i:s'),
    //         $endDate->format('Y-m-d H:i:s')
    //     ]);

    //     $total_sub_amount = (float) ($summary->total_sub_amount ?? 0);
    //     $total_sub_count = (float) ($summary->total_sub ?? 0);

    //     $total_tax_rate = $tax_on_subscription->sum(fn($tax) => (float) $tax->tax_rate);

    //     $total_tax_amount = ($total_sub_amount * $total_tax_rate) / 100;
    //     $tax_on_subscription = $tax_on_subscription->toArray();

    //     $subData = SubscriptionTransaction::where('is_trial', 0)
    //         ->whereBetween('created_at', [$startDate, $endDate]);
    //     if ($export === false) {
    //         $subData = $subData->paginate(config('default_pagination'));
    //         $subData->getCollection()->transform(function ($transaction) use ($tax_on_subscription) {
    //             $taxes = [];

    //             foreach ($tax_on_subscription as $tax) {
    //                 $rate = (float) $tax['tax_rate'];
    //                 $amount = ($transaction->paid_amount * $rate) / 100;

    //                 $taxes[] = [
    //                     'tax_name' => $tax['name'],
    //                     'tax_rate' => $rate,
    //                     'tax_amount' => round($amount, 2),
    //                 ];
    //             }

    //             $transaction->calculated_taxes = $taxes;
    //             return $transaction;
    //         });
    //     } else {
    //         $subData = $subData->cursor()->map(function ($transaction) use ($tax_on_subscription) {

    //             $calculatedTaxes = collect($tax_on_subscription)->map(function ($tax) use ($transaction) {
    //                 $rate = (float) $tax['tax_rate'];
    //                 return [
    //                     'tax_name' => $tax['name'],
    //                     'tax_rate' => $rate,
    //                     'tax_amount' => round(($transaction->paid_amount * $rate) / 100, 2)
    //                 ];
    //             })->all();

    //             $transaction->calculated_taxes = $calculatedTaxes;
    //             return $transaction;
    //         });
    //     }



    //     return [
    //         'data' => $subData,
    //         'total_tax_amount' => $total_tax_amount,
    //         'total_amount' => $total_sub_amount,
    //         'total_count' => $total_sub_count,
    //         'total_tax_rate' => $total_tax_rate,

    //     ];
    // }
    private function getAdminCommissionTaxData($startDate, $endDate, $tax_data, $export = false)
    {
        $results = DB::selectOne("  SELECT
                COUNT(*) AS total_count,
                SUM(trip_amount) AS total_order_amount,
                SUM(admin_commission) AS admin_commission
            FROM trip_transactions
            WHERE status IS NULL
            AND created_at BETWEEN ? AND ?
            LIMIT 1
        ", [
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s')
        ]);

        $total_count = $results->total_count;
        $total_order_amount = $results->total_order_amount;
        $admin_commission = $results->admin_commission;

        $total_tax_amount = 0;
        $orderData = TripTransaction::whereNull('status')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('trip_id', 'trip_amount', 'tax', 'admin_commission','additional_charge');


        if ($export === false) {

            $orderData = $orderData->paginate(config('default_pagination'));
            $orderData->getCollection()->transform(function ($transaction) use ($tax_data, &$total_tax_amount) {
                $taxes = [];
                foreach ($tax_data as $tax) {
                    $rate = (float) $tax['tax_rate'];
                    $amount = (($transaction->admin_commission ) * $rate) / 100;

                    $taxes[] = [
                        'tax_name' => $tax['name'],
                        'tax_rate' => $rate,
                        'tax_amount' => round($amount, 2),
                    ];
                    $total_tax_amount += $amount;
                }

                $transaction->calculated_taxes = $taxes;
                return $transaction;
            });
        } else {
            $orderData = $orderData->cursor()->map(function ($transaction) use ($tax_data, &$total_tax_amount) {
                $taxes = [];

                foreach ($tax_data as $tax) {
                    $rate = (float) $tax['tax_rate'];
                    $amount = ((  $transaction->admin_commission) * $rate) / 100;

                    $taxes[] = [
                        'tax_name' => $tax['name'],
                        'tax_rate' => $rate,
                        'tax_amount' => round($amount, 2),
                    ];
                    $total_tax_amount += $amount;
                }

                $transaction->calculated_taxes = $taxes;
                return $transaction;
            });
        }
        return [
            'data' => $orderData,
            'total_count' => $total_count,
            'total_amount' => $admin_commission,
            'total_tax_amount' => $total_tax_amount,
            'total_order_amount' => $total_order_amount,
        ];
    }

    private function getServiceChargeTaxData($startDate, $endDate, $tax_data, $export = false)
    {
        $results = DB::selectOne(" SELECT
                    COUNT(*) AS total_count,

                    SUM(trip_amount) AS total_order_amount,
                      SUM(additional_charge)  AS additional_charge
                FROM trip_transactions
                WHERE status IS NULL
                    AND created_at BETWEEN ? AND ?
                    LIMIT 1
                ", [
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s')
        ]);

        $total_count = $results->total_count;

        $total_order_amount = $results->total_order_amount;
        $additional_charge = $results->additional_charge;

        $total_tax_amount = 0;
        $orderData = TripTransaction::whereNull('status')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('trip_id', 'trip_amount', 'additional_charge');


        if ($export === false) {
            $orderData =  $orderData->paginate(config('default_pagination'));
            $orderData->getCollection()->transform(function ($transaction) use ($tax_data, &$total_tax_amount) {
                $taxes = [];

                foreach ($tax_data as $tax) {
                    $rate = (float) $tax['tax_rate'];
                    $amount = (($transaction->additional_charge) * $rate) / 100;
                    $taxes[] = [
                        'tax_name' => $tax['name'],
                        'tax_rate' => $rate,
                        'tax_amount' => round($amount, 2),
                    ];
                    $total_tax_amount += $amount;
                }

                $transaction->calculated_taxes = $taxes;
                return $transaction;
            });
        } else {
            $orderData = $orderData->cursor()->map(function ($transaction) use ($tax_data, &$total_tax_amount) {
                $taxes = [];

                foreach ($tax_data as $tax) {
                    $rate = (float) $tax['tax_rate'];
                    $amount = (($transaction->additional_charge) * $rate) / 100;
                    $taxes[] = [
                        'tax_name' => $tax['name'],
                        'tax_rate' => $rate,
                        'tax_amount' => round($amount, 2),
                    ];
                    $total_tax_amount += $amount;
                }

                $transaction->calculated_taxes = $taxes;
                return $transaction;
            });
        }

        return [
            'data' => $orderData,
            'total_tax_amount' => $total_tax_amount,
            'total_count' => $total_count ?? 0,
            'total_amount' => $additional_charge,
            'total_order_amount' => $total_order_amount,
        ];
    }


    public function adminTaxDetailsExport(Request $request)
    {
        $date_range_type = $request->date_range_type;

        if ($date_range_type == 'this_fiscal_year') {
            $dateRange = now()->startOfYear()->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        } else {
            $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        }

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        $taxSource = $request->source;
        $taxRates =  $this->getTaxRates($request);

        $tax_on_subscription = $taxRates['tax_on_subscription'];
        $tax_on_service_charge = $taxRates['tax_on_service_charge'];
        $tax_on_order_commission = $taxRates['tax_on_order_commission'];

        if ($taxSource == 'vendor_subscription') {
            $data =  $this->getSubsctiprionData($startDate, $endDate, $tax_on_subscription, true);
        } elseif ($taxSource == 'admin_commission') {
            $data =  $this->getAdminCommissionTaxData($startDate, $endDate, $tax_on_order_commission, true);
        }   else {
            $data =  $this->getServiceChargeTaxData($startDate, $endDate, $tax_on_service_charge, true);
        }



        $total_tax_amount = $data['total_tax_amount'];
        $total_amount = $data['total_amount'];
        $total_count = $data['total_count'];
        $total_tax_rate = $data['total_tax_rate'] ?? 0;
        $total_order_amount = $data['total_order_amount'] ?? 0;
        $taxData = $data['data'];



        $startDate = Carbon::parse($startDate)->toIso8601String();
        $endDate = Carbon::parse($endDate)->toIso8601String();
        $data = [
            'taxData' => $taxData,
            'search' => $request->search ?? null,
            'from' => $startDate,
            'to' => $endDate,
            'total_tax_amount' => $total_tax_amount,
            'total_amount' => $total_amount,
            'total_count' => $total_count,
            'total_tax_rate' => $total_tax_rate,
            'total_order_amount' => $total_order_amount,
            'taxSource' => $taxSource
        ];


        if ($request->export_type == 'excel') {
            return Excel::download(new AdminTripTaxReportDetailsExport($data), $taxSource . ' ' . 'TaxExport.xlsx');
        } else if ($request->export_type == 'csv') {
            return Excel::download(new AdminTripTaxReportDetailsExport($data), $taxSource . ' ' . 'TaxExport.csv');
        }
    }
    public function adminTaxReportExport(Request $request)
    {

        $date_range_type = $request->date_range_type;

        if ($date_range_type == 'this_fiscal_year') {
            $dateRange = now()->startOfYear()->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        } else {
            $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        }

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();


        $taxRates =  $this->getTaxRates($request);

        $tax_on_subscription = $taxRates['tax_on_subscription'];
         $tax_on_service_charge = $taxRates['tax_on_service_charge'];
         $tax_on_order_commission = $taxRates['tax_on_order_commission'];


        $orderTaxData = $this->getOrderTaxes($tax_on_service_charge, $tax_on_order_commission,  $startDate, $endDate);

        // $taxOnSubscriptionData = $this->generateSubscriotionTax($tax_on_subscription, $startDate, $endDate);

        // if ($taxOnSubscriptionData) {

        //     $combinedResults = array_merge(
        //         $orderTaxData,
        //         [
        //             'vendor_subscription' => [
        //                 'total_base_amount' => array_reduce($taxOnSubscriptionData, fn($carry, $item) =>  $item->total_paid_amount, 0),
        //                 'taxes' => array_reduce($taxOnSubscriptionData, function ($carry, $item) {
        //                     $carry[$item->tax_name][] = [
        //                         'tax_rate' => $item->tax_rate,
        //                         'total_tax_amount' => $item->total_tax
        //                     ];
        //                     return $carry;
        //                 }, [])
        //             ]
        //         ]
        //     );
        // } else {
        //     $combinedResults = $orderTaxData;
        // }
        $combinedResults = $orderTaxData;
        $totalBase = 0;
        $totalTax = 0;

        foreach ($combinedResults as $category) {
            $totalBase += $category['total_base_amount'];
            foreach ($category['taxes'] as $taxGroup) {
                foreach ($taxGroup as $tax) {
                    $totalTax += $tax['total_tax_amount'];
                }
            }
        }


        $startDate = Carbon::parse($startDate)->toIso8601String();
        $endDate = Carbon::parse($endDate)->toIso8601String();
        $data = [
            'taxData' => $combinedResults,
            'search' => $request->search ?? null,
            'from' => $startDate,
            'to' => $endDate,
            'total_tax_amount' => $totalTax,
            'total_amount' => $totalBase,

        ];


        if ($request->export_type == 'excel') {
            return Excel::download(new AdminTaxReportExport($data), 'AdminTripTaxExport.xlsx');
        } else if ($request->export_type == 'csv') {
            return Excel::download(new AdminTaxReportExport($data), 'AdminTripTaxExport.csv');
        }
    }
}
