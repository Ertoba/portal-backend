<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;

use App\Models\Store;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Entities\TripTransaction;

class DashboardController extends Controller
{

    /**
     * @param Request $request
     * @return Application|Factory|View|RedirectResponse|JsonResponse
     */
    public function dashboard(Request $request): Application|Factory|View|RedirectResponse|JsonResponse
    {
        $zone_id = $request->input('zone_id', 'all');
        $statistics_type = $request->input('statistics_type', 'all');
        $statistics_chart_type = $request->input('statistics_chart_type', 'all');

        $tripQuery = Trips::when($zone_id != 'all', function ($query) use ($zone_id) {
            return $query->Zone($zone_id);
        });

        if ($statistics_type == 'this_year') {
            $tripQuery->whereYear('created_at', now()->year);
        } elseif ($statistics_type == 'this_month') {
            $tripQuery->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($statistics_type == 'this_week') {
            $tripQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        }

        if ($statistics_chart_type == 'this_year') {
            $tripQuery->whereYear('created_at', now()->year);
        } elseif ($statistics_chart_type == 'this_month') {
            $tripQuery->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($statistics_chart_type == 'this_week') {
            $tripQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        }

        $typeCounts = (clone $tripQuery)->selectRaw('trip_type, count(*) as aggregate')
            ->groupBy('trip_type')->get()->pluck('aggregate', 'trip_type');

        $distanceWiseCount = $typeCounts['distance_wise'] ?? 0;
        $hourlyCount = $typeCounts['hourly'] ?? 0;
        $daywiseCount = $typeCounts['day_wise'] ?? 0;

        $statusCounts = Trips::statusCounts($tripQuery);

        $totalCount = $statusCounts->sum();
        $pendingCount = $statusCounts['pending'] ?? 0;
        $confirmedCount = $statusCounts['confirmed'] ?? 0;
        $ongoingCount = $statusCounts['ongoing'] ?? 0;
        $completedCount = $statusCounts['completed'] ?? 0;
        $canceledCount = $statusCounts['canceled'] ?? 0;
        $zoneName = $zone_id == 'all' ? 'All' : Zone::where('id', $zone_id)->value('name');

        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'settings') {
            return redirect()->route('admin.business-settings.business-setup');
        }

        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $total_subs = $data['total_subs'];
        $topCustomers = $data['top_customers'];
        $topProviders = $data['top_providers'];
        $label = $data['label'];
        $grossEarning = collect($total_sell)->sum();
        if ($request->ajax()) {
            return response()->json([
                'delivery_statistics' => view('rental::admin.partials.delivery-statistics', compact('pendingCount', 'confirmedCount', 'ongoingCount', 'completedCount', 'canceledCount', 'totalCount'))->render(),
                'top_providers' => view('rental::admin.partials.top-providers', compact('topProviders'))->render(),
                'top_customers' => view('rental::admin.partials.top-customers', compact('topCustomers'))->render(),
                'sale_chart' => view('rental::admin.partials.sale-chart', compact('total_sell', 'commission', 'total_subs','label'))->render(),
                'by_trip_type' => view('rental::admin.partials.by-trip-type', compact('hourlyCount', 'distanceWiseCount', 'totalCount','daywiseCount'))->render(),
                'zoneName' => $zoneName,
                'daywiseCount' => $daywiseCount,
                'hourlyCount' => $hourlyCount,
                'distanceWiseCount' => $distanceWiseCount,
                'totalCount' => $totalCount,
                'total_sell' => array_map(function($val) {
                    return number_format((float)$val, 2, '.', '');
                }, array_values($total_sell)),
                'commission' => array_map(function($val) {
                    return number_format((float)$val, 2, '.', '');
                }, array_values($commission)),
                'total_subs' => array_map(function($val) {
                    return number_format((float)$val, 2, '.', '');
                }, array_values($total_subs)),
            'labels' => array_map(function($val) {
                    return trim($val, '"');
                }, $label),
                'grossEarning' => number_format((float)$grossEarning, 2, '.', '')
            ], 200);
        }

        return view("rental::admin.dashboard-{$module_type}", compact(
            'pendingCount',
            'confirmedCount',
            'ongoingCount',
            'completedCount',
            'canceledCount',
            'totalCount',
            'zoneName',
            'total_sell', 'commission', 'total_subs', 'topCustomers', 'topProviders', 'label', 'distanceWiseCount', 'hourlyCount','daywiseCount'
        ));
    }

    /**
     * @param Request $request
     * @return Application|Factory|View|RedirectResponse|JsonResponse
     */
    public function byTripType(Request $request): Application|Factory|View|RedirectResponse|JsonResponse
    {
        $zone_id = $request->input('zone_id', 'all');
        $type = $request->input('trip_overview', 'all');

        $tripQuery = Trips::when($zone_id != 'all', function ($query) use ($zone_id) {
            return $query->Zone($zone_id);
        });

        if ($type == 'this_year') {
            $tripQuery->whereYear('created_at', now()->year);
        } elseif ($type == 'this_month') {
            $tripQuery->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($type == 'this_week') {
            $tripQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        }

        $totalCount = $tripQuery->count();
        $distanceWiseCount = (clone $tripQuery)->where('trip_type', 'distance_wise')->count();
        $hourlyCount = (clone $tripQuery)->where('trip_type', 'hourly')->count();
        $daywiseCount =  (clone $tripQuery)->where('trip_type', 'day_wise')->count() ;

        return response()->json([
            'view' => view('rental::admin.partials.by-trip-type', compact('hourlyCount', 'distanceWiseCount','daywiseCount' ,'totalCount'))->render(),
            'hourlyCount' => $hourlyCount,
            'daywiseCount' => $daywiseCount,
            'distanceWiseCount' => $distanceWiseCount,
            'totalCount' => $totalCount
        ], 200);
    }

    /**
     * @param Request $request
     * @return Application|Factory|View|RedirectResponse|JsonResponse
     */
    public function commissionOverview(Request $request): Application|Factory|View|RedirectResponse|JsonResponse
    {
        $request->input('zone_id', 'all');
        $request->input('commission_overview', 'all');

        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $total_subs = $data['total_subs'];
        $label = $data['label'];
        $grossEarning = collect($total_sell)->sum();

        return response()->json([
            'view' => view('rental::admin.partials.sale-chart', compact('total_sell', 'commission', 'total_subs', 'label', 'grossEarning'))->render(),
            'grossEarning' => $grossEarning,
            'total_sell' => array_map(function($val) {
                return number_format((float)$val, 2, '.', '');
            }, array_values($total_sell)),
            'commission' => array_map(function($val) {
                return number_format((float)$val, 2, '.', '');
            }, array_values($commission)),
            'total_subs' => array_map(function($val) {
                return number_format((float)$val, 2, '.', '');
            }, array_values($total_subs)),
           'labels' => array_map(function($val) {
                return trim($val, '"');
            }, $label)
                ], 200);
    }
    private function bucketAggregate($value)
    {
        if (! $value) {
            return 0;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return ! str_contains((string) $value, '.') ? (int) $value : (float) $value;
    }

    private function tripTransactionBucketSums(array $buckets, $request): array
    {
        $select = [];
        $bindings = [];

        foreach (['trip_amount' => 's', 'admin_commission + admin_expense' => 'c'] as $expression => $prefix) {
            foreach ($buckets as $key => [$condition, $binds]) {
                $select[] = "sum(case when {$condition} then {$expression} else 0 end) as {$prefix}_{$key}";

                foreach ($binds as $bind) {
                    $bindings[] = $bind;
                }
            }
        }

        $row = TripTransaction::when(is_numeric($request['module_id']), function ($q) use ($request) {
                return $q->where('module_id', $request['module_id']);
            })
            ->when(is_numeric($request['zone_id']), function ($q) use ($request) {
                return $q->where('zone_id', $request['zone_id']);
            })
            ->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->first();

        $total_sell = [];
        $commission = [];

        foreach (array_keys($buckets) as $key) {
            $total_sell[$key] = $this->bucketAggregate($row?->{"s_{$key}"});
            $commission[$key] = $this->bucketAggregate($row?->{"c_{$key}"});
        }

        return [$total_sell, $commission];
    }

    private function subscriptionBucketSums(array $buckets, $request): array
    {
        $select = [];
        $bindings = [];

        foreach ($buckets as $key => [$condition, $binds]) {
            $select[] = "sum(case when {$condition} then paid_amount else 0 end) as p_{$key}";

            foreach ($binds as $bind) {
                $bindings[] = $bind;
            }
        }

        $row = SubscriptionTransaction::when(is_numeric($request['zone_id']), function ($q) use ($request) {
                return $q->whereHas('store', function ($query) use ($request) {
                    return $query->where('zone_id', $request['zone_id'])->where('module_id', $request['module_id']);
                });
            })
            ->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->first();

        $total_subs = [];

        foreach (array_keys($buckets) as $key) {
            $total_subs[$key] = $this->bucketAggregate($row?->{"p_{$key}"});
        }

        return $total_subs;
    }

    public function dashboard_data($request)
    {
        // Ranked from the trips side: the previous join plus withCount('trips'), ordered on
        // the alias, could not terminate early and was still running after 690s against 2M
        // trips to produce five rows. is_guest = 0 mirrors the User::trips() relation.
        $topTripCounts = DB::table('trips')
            ->selectRaw('user_id, COUNT(*) AS trip_count')
            ->where('is_guest', 0)
            ->whereNotNull('user_id')
            ->when(is_numeric($request['zone_id']), function ($query) use ($request) {
                // Users in the zone, as the original users.zone_id filter meant -- trips
                // carry the provider's zone.
                return $query->whereIn('user_id', User::select('id')->where('zone_id', $request['zone_id']));
            })
            ->groupBy('user_id')
            ->orderByDesc('trip_count')
            ->limit(5)
            ->pluck('trip_count', 'user_id');

        $topCustomers = User::withStorage()
            ->whereIn('id', $topTripCounts->keys())
            ->get()
            ->each(function ($customer) use ($topTripCounts) {
                // Both names are set: the view reads trip_count, and trips_count is what
                // withCount('trips') used to expose.
                $count = (int) ($topTripCounts[$customer->id] ?? 0);
                $customer->trip_count = $count;
                $customer->trips_count = $count;
            })
            ->sortByDesc('trip_count')
            ->values();


        $topProvider = Store::withStorage()->withcount('trips')
            ->when(is_numeric($request['module_id']), function ($q) use ($request) {
                return $q->where('module_id', $request['module_id']);
            })
            ->when(is_numeric($request['zone_id']), function ($q) use ($request) {
                return $q->where('zone_id', $request['zone_id']);
            })
            ->having('trips_count', '>', 0)
            ->orderBy('trips_count', 'desc')
            ->take(5)
            ->get();



        $months = array(
            '"Jan"',
            '"Feb"',
            '"Mar"',
            '"Apr"',
            '"May"',
            '"Jun"',
            '"Jul"',
            '"Aug"',
            '"Sep"',
            '"Oct"',
            '"Nov"',
            '"Dec"'
        );
        $days = array(
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"',
            '"Sun"',
        );
        $total_sell = [];
        $commission = [];

        switch ($request['commission_overview']) {
            case "this_year":
                $buckets = [];
                for ($i = 1; $i <= 12; $i++) {
                    $buckets[$i] = ['month(`created_at`) = ? and year(`created_at`) = ?', [$i, now()->format('Y')]];
                }
                $label = $months;

                break;

            case "this_week":
                $weekStartDate = now()->startOfWeek();
                $buckets = [];
                for ($i = 0; $i < 7; $i++) {
                    $buckets[$i] = ['date(`created_at`) = ?', [$weekStartDate->copy()->addDays($i)->format('Y-m-d')]];
                }
                $label = $days;

                break;

            case "this_month":
                $start = now()->startOfMonth();
                $total_days = now()->daysInMonth;
                $weeks = array(
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . $total_days . '"',
                );

                $buckets = [];
                for ($i = 1; $i <= 4; $i++) {
                    $end = $start->copy()->addDays(6);

                    if ($i == 4) {
                        $end = now()->endOfMonth();
                    }

                    $buckets[$i] = ['`created_at` between ? and ?', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"]];

                    $start = $end->copy()->addDay();
                }

                $label = $weeks;

                break;

            default:
                $buckets = [];
                for ($i = 1; $i <= 12; $i++) {
                    $buckets[$i] = ['month(`created_at`) = ? and year(`created_at`) = ?', [$i, now()->format('Y')]];
                }
                $label = $months;

        }

        [$total_sell, $commission] = $this->tripTransactionBucketSums($buckets, $request);
        $total_subs = $this->subscriptionBucketSums($buckets, $request);

        $dash_data['top_providers'] = $topProvider;
        $dash_data['top_customers'] = $topCustomers;
        $dash_data['total_sell'] = $total_sell;
        $dash_data['commission'] = $commission;
        $dash_data['total_subs'] = $total_subs;
        $dash_data['label'] = $label;

        return $dash_data;
    }

}
