<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Zone;
use App\Models\Store;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;
use App\Traits\Report\ExportRowStreamTrait;
use App\Models\BusinessSetting;
use App\Models\Module;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Trips;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Entities\Vehicle;
use Modules\Rental\Entities\TripTransaction;
use Modules\Rental\Entities\VehicleCategory;
use Modules\Rental\Exports\TripReportExport;
use Modules\Rental\Exports\VehicleReportExport;
use Modules\Rental\Exports\TransactionReportExport;
use Modules\Rental\Exports\ProviderTripReportExport;
use Modules\Rental\Exports\ProviderSalesReportExport;
use Modules\Rental\Exports\ProviderSummaryReportExport;

class ReportController extends Controller
{
    use ExportRowStreamTrait;

    public function transactionReport(Request $request)
    {

        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $key = explode(' ', $request['search'] ?? '');
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider_id = $request->query('provider_id', 'all');
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        $filter = $request->query('filter', 'all_time');


        $data=$this->getTransactionData($request);
        $tripTransactions = $data['tripTransactions']->paginate(config('default_pagination'))->withQueryString();

        $adminEarned = $data['earnings']->admin_earned;
        $providerEarned =  $data['earnings']->provider_earned;
        $totalAmount =  $data['earnings']->total_amount;

        return view('rental::admin.report.transaction-report', compact('tripTransactions', 'zone', 'provider', 'filter', 'adminEarned', 'providerEarned','key','totalAmount'));
    }


        private function getTransactionData($request){

            if (session()->has('from_date') == false) {
                session()->put('from_date', date('Y-m-01'));
                session()->put('to_date', date('Y-m-30'));
            }
            $key = explode(' ', $request['search'] ?? '');
            $from = session('from_date');
            $to = session('to_date');
            $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
            $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
            $provider_id = $request->query('provider_id', 'all');
            $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
            $filter = $request->query('filter', 'all_time');


            $tripTransactions = TripTransaction::with('trip', 'trip.trip_details', 'trip.customer', 'trip.provider', 'trip.proDiscount')->when(isset($zone), function ($query) use ($zone) {
                return $query->where('zone_id', $zone->id);
            })
                ->when($request['search'], function ($query) use ($key) {
                    return $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('trip_id', 'like', "%{$value}%");
                        }
                    });
                })
                ->when(isset($provider), function ($query) use ($provider) {
                    return $query->where('provider_id', $provider->id);
                })
                ->when(request('module_id'), function ($query) {
                    return $query->module(request('module_id'));
                })
                ->applyDateFilter($filter, $from, $to)
                ->orderBy('created_at', 'desc');


            $earnings = TripTransaction::when(isset($zone), function ($query) use ($zone) {
                return $query->where('zone_id', $zone->id);
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('trip_id', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(request('module_id'), function ($query) {
                return $query->module(request('module_id'));
            })
            ->applyDateFilter($filter, $from, $to)
            ->select(
                DB::raw('SUM(admin_net_income) as admin_earned'),
                DB::raw('SUM(store_amount) as provider_earned'),
                DB::raw('SUM(trip_amount) as total_amount')
            )
            ->first();

            return [ 'tripTransactions'=>$tripTransactions, 'earnings'=> $earnings];
        }


    public function transactionExport(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $filter = $request->query('filter', 'all_time');

        $data=$this->getTransactionData($request);
        $tripTransactions = $data['tripTransactions']->get();

        $adminEarned = $data['earnings']->admin_earned;
        $providerEarned =  $data['earnings']->provider_earned;
        $totalAmount =  $data['earnings']->total_amount;

        $data = [
            'tripTransactions'=>$tripTransactions,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'provider'=>is_numeric($provider_id)?Helpers::get_stores_name($provider_id):null,
            'adminEarned'=>$adminEarned,
            'providerEarned'=>$providerEarned,
            'totalAmount'=>$totalAmount,
            'filter'=>$filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new TransactionReportExport($data), 'TransactionReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new TransactionReportExport($data), 'TransactionReport.csv');
        }
    }

    public function tripReport(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider_id = $request->query('provider_id', 'all');
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');

        $filters = fn () => $this->tripReportQuery($request, $zone, $provider, $customer, $filter, $from, $to);

        $trips = $filters()->with(['customer', 'provider', 'trip_details', 'trip_transaction', 'proDiscount'])
            ->orderBy('schedule_at', 'desc')->paginate(config('default_pagination'))->withQueryString();

        // Counted in SQL. This used to run the same filters a second time with ->get() and
        // count the resulting collection in PHP, hydrating every matching trip -- 2M rows and
        // their relations to produce five numbers, which exhausted the memory limit outright.
        // The collection was never used for anything else; the view only reads the counts.
        $counts = $filters()->selectRaw('trip_status, COUNT(*) AS total')
            ->groupBy('trip_status')->pluck('total', 'trip_status');

        $countOf = fn (array $statuses) => (int) array_sum(array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses));

        $total_canceled_count = $countOf(['canceled']);
        $total_completed_count = $countOf(['completed']);
        $total_progress_count = $countOf(['confirmed', 'pending']);
        $total_ongoing_count = $countOf(['ongoing']);
        // 'failed' is not one of the trip_status enum values (the enum has 'payment_failed'),
        // so this has always been zero. Kept as-is rather than silently changing the number.
        $total_failed_count = $countOf(['failed']);

        return view('rental::admin.report.trip-report', compact('trips', 'zone', 'provider', 'filter', 'customer', 'total_ongoing_count', 'total_failed_count', 'total_progress_count', 'total_canceled_count', 'total_completed_count'));
    }

    /**
     * The trip-report filter set, shared by the paginated list and the status counts so the
     * two can never diverge. Date filters resolve to explicit ranges rather than
     * whereYear()/whereMonth(), which render as year(schedule_at) = ? and cannot use an index.
     */
    /**
     * The date window a report filter means, as ['Y-m-d H:i:s', 'Y-m-d H:i:s'] or [null, null]
     * for no bound. An explicit range so the schedule_at index stays usable -- the
     * whereYear()/whereMonth() forms this replaces render as year(schedule_at) = ?.
     */
    private function tripDateRange($filter, $from = null, $to = null): array
    {
        [$start, $end] = match (true) {
            $filter == 'custom' && $from && $to => [$from.' 00:00:00', $to.' 23:59:59'],
            $filter == 'this_year' => [now()->startOfYear(), now()->endOfYear()],
            $filter == 'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            $filter == 'previous_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            $filter == 'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            default => [null, null],
        };

        return [
            $start instanceof \DateTimeInterface ? $start->format('Y-m-d H:i:s') : $start,
            $end instanceof \DateTimeInterface ? $end->format('Y-m-d H:i:s') : $end,
        ];
    }

    private function tripReportQuery(Request $request, $zone, $provider, $customer, $filter, $from, $to)
    {
        $key = explode(' ', $request['search'] ?? '');

        [$start, $end] = $this->tripDateRange($filter, $from, $to);

        return Trips::when(request('module_id'), function ($query) {
            return $query->module(request('module_id'));
        })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->where('zone_id', $zone->id);
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($customer), function ($query) use ($customer) {
                return $query->where('user_id', $customer->id);
            })
            ->when($start && $end, function ($query) use ($start, $end) {
                return $query->whereBetween('schedule_at', [$start, $end]);
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            });
    }

    public function tripReportExport(Request $request)
    {

        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider_id = $request->query('provider_id', 'all');
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');

        // The same filter set the trip report page runs, rather than a second copy of the
        // chain -- the copy still carried the whereYear()/whereMonth() forms that cannot use
        // the schedule_at index, and a duplicated this_month branch, so the export and the
        // page it exports could disagree.
        $tripQuery = $this->tripReportQuery($request, $zone, $provider, $customer, $filter, $from, $to)
            ->with(['customer', 'provider', 'trip_details', 'trip_transaction', 'proDiscount'])
            ->orderBy('schedule_at', 'desc')
            // Unique tiebreaker: forPage() re-sorts on every page, so a tied schedule_at
            // could leave one row on both sides of a boundary and drop another.
            ->orderBy('id');

        // Streamed rather than ->get(): hydrating 2M trips with five relations at once
        // exhausted the memory limit outright. Counted once -- count() on the LazyCollection
        // would re-run every chunk query.
        $trips_count = (clone $tripQuery)->count();

        $data = [
            'trips'=>$this->streamExportRows($tripQuery),
            'trips_count'=>$trips_count,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'provider'=>is_numeric($provider_id)?Helpers::get_stores_name($provider_id):null,
            'customer'=>is_numeric($customer_id)?Helpers::get_customer_name($customer_id):null,
            'filter'=>$filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new TripReportExport($data), 'TripReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new TripReportExport($data), 'TripReport.csv');
        }
    }
    public function vehicleReport(Request $request)
    {
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        $category = is_numeric($category_id) ? VehicleCategory::findOrFail($category_id) : null;
        $vehicles = $this->get_vehicle_data($request);
        $vehicles =  $vehicles->paginate(config('default_pagination'))->withQueryString();

        return view('rental::admin.report.vehicle-wise-report', compact('zone', 'provider', 'category', 'vehicles', 'filter'));
    }
    public function vehicleReportExport(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $vehicles = $this->get_vehicle_data($request);
        $vehicles =  $vehicles->get();

        $data = [
            'vehicles'=>$vehicles,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'provider'=>is_numeric($provider_id)?Helpers::get_stores_name($provider_id):null,
            'category'=>is_numeric($category_id)?Helpers::get_category_name($category_id):null,
            'module'=>request('module_id')?Helpers::get_module_name(request('module_id')):null,
            'filter'=>$filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new VehicleReportExport($data), 'VehicleReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new VehicleReportExport($data), 'VehicleReport.csv');
        }
    }
    private static function get_vehicle_data($request){

        $key = explode(' ', $request['search'] ?? '');
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        $category = is_numeric($category_id) ? VehicleCategory::findOrFail($category_id) : null;

        $vehicles =Vehicle::withStorage()->withCount([
                'tripDetails as trips_count' => function ($query) use ($from, $to, $filter) {
                    $query->whereHas('trip', function ($query) {
                        return $query->whereIn('trip_status', ['completed']);
                    })->applyDateFilter($filter, $from, $to);
                }, 'vehicleIdentities'
            ] )
            ->withSum([
                'tripDetails' => function ($query) use ($from, $to, $filter) {
                    $query->whereHas('trip', function ($query) {
                        return $query->whereIn('trip_status', ['completed']);
                    })->applyDateFilter($filter, $from, $to);
                },
            ], 'quantity')
            ->addSelect([
                'total_discount' => function ($query) use ($from, $to, $filter) {
                    $query->selectRaw('SUM(trip_details.discount_on_trip * trip_details.quantity)')
                        ->from('trip_details')
                        ->join('trips', 'trips.id', '=', 'trip_details.trip_id')
                        ->whereColumn('trip_details.vehicle_id', 'vehicles.id')
                        ->whereIn('trips.trip_status', ['completed'])
                        ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                            return $query->whereBetween('trip_details.created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
                        })
                        ->when(isset($filter) && $filter == 'this_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'this_month', function ($query) {
                            return $query->whereMonth('trip_details.created_at', now()->format('m'))
                                ->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', date('Y') - 1);
                        })
                        ->when(isset($filter) && $filter == 'this_week', function ($query) {
                            return $query->whereBetween('trip_details.created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
                        });
                },
                'trips_sum_price' => function ($query) use ($from, $to, $filter) {
                    $query->selectRaw('SUM(trip_details.price * trip_details.quantity)')
                        ->from('trip_details')
                        ->join('trips', 'trips.id', '=', 'trip_details.trip_id')
                        ->whereColumn('trip_details.vehicle_id', 'vehicles.id')
                        ->whereIn('trips.trip_status', ['completed'])
                        ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                            return $query->whereBetween('trip_details.created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
                        })
                        ->when(isset($filter) && $filter == 'this_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'this_month', function ($query) {
                            return $query->whereMonth('trip_details.created_at', now()->format('m'))
                                ->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', date('Y') - 1);
                        })
                        ->when(isset($filter) && $filter == 'this_week', function ($query) {
                            return $query->whereBetween('trip_details.created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
                        });
                },
            ])
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('provider_id', $zone->stores->pluck('id'));
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($category), function ($query) use ($category) {
                return $query->where('category_id', $category->id);
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->with('provider')
            ->having('trips_count', '>' ,0)
            ->orderBy('trips_count', 'desc');

        return $vehicles;
    }
    public function set_date(Request $request)
    {
        session()->put('from_date', date('Y-m-d', strtotime($request['from'])));
        session()->put('to_date', date('Y-m-d', strtotime($request['to'])));
        return back();
    }
    public function providerSummaryReport(Request $request)
    {
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
            '"Sun"',
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"'
        );

        $key = explode(' ', $request['search'] ?? '');

        $filter = $request->query('filter', 'all_time');

        [$rangeStart, $rangeEnd] = $this->tripDateRange($filter);

        // The four per-provider numbers the view needs, grouped once on the trips side and
        // left-joined in. Two earlier shapes both failed here: with('trips') pulled every trip
        // of every provider on the page into memory and exhausted the limit, and withCount()
        // ran four correlated subqueries per provider across all 5k of them before sorting on
        // the alias, which cannot terminate early -- measured at 8.9s for one page. Grouping
        // trips once is a single pass over the (provider_id, trip_status) index.
        //
        // Left join, not inner: a provider with no trips still belongs in the report, and the
        // COALESCE keeps its columns at 0 rather than null.
        $tripAggregates = Trips::selectRaw("
                provider_id,
                COUNT(*) AS trips_count,
                COALESCE(SUM(trip_status = 'completed'), 0) AS completed_trips_count,
                COALESCE(SUM(trip_status = 'canceled'), 0) AS canceled_trips_count,
                COALESCE(SUM(CASE WHEN trip_status = 'completed' THEN trip_amount ELSE 0 END), 0) AS completed_trips_amount
            ")
            ->when($rangeStart && $rangeEnd, fn ($q) => $q->whereBetween('schedule_at', [$rangeStart, $rangeEnd]))
            ->groupBy('provider_id');

        $providers = Store::leftJoinSub($tripAggregates, 'trip_aggregates', function ($join) {
            $join->on('trip_aggregates.provider_id', '=', 'stores.id');
        })
            ->whereHas('vendor', function ($query) {
                $query->where('status', 1);
            })
            ->withModuleType('rental')
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->select([
                'stores.*',
                DB::raw('COALESCE(trip_aggregates.trips_count, 0) AS trips_count'),
                DB::raw('COALESCE(trip_aggregates.completed_trips_count, 0) AS completed_trips_count'),
                DB::raw('COALESCE(trip_aggregates.canceled_trips_count, 0) AS canceled_trips_count'),
                DB::raw('COALESCE(trip_aggregates.completed_trips_amount, 0) AS completed_trips_amount'),
            ])
            ->orderBy('trips_count', 'DESC')->paginate(config('default_pagination'));

        $new_providers = Store::withModuleType('rental')->whereHas('vendor',function($query){
                $query->where('status',1);
            })
            ->applyDateFilter($filter)
            ->count();

        $trip_payment_methods = Trips::when(isset($filter) && $filter == 'this_year', function ($query) {
            return $query->whereYear('schedule_at', now()->format('Y'));
        })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->Completed()
            ->selectRaw(DB::raw("sum(`trip_amount`) as total_trip_amount, count(*) as trip_count, IF((`payment_method`='cash_payment'), `payment_method`, IF(`payment_method`='wallet',`payment_method`, 'digital_payment')) as 'payment_methods'"))->groupBy('payment_methods')
            ->get();

        // One aggregate row instead of hydrating every matching trip and counting the
        // collection in PHP. 'accepted' and 'failed' are not trip_status enum values (the enum
        // has 'processing' and 'payment_failed'), so they contribute nothing here either --
        // kept in the lists rather than silently changing what the cards report.
        $totals = Trips::when($rangeStart && $rangeEnd, fn ($q) => $q->whereBetween('schedule_at', [$rangeStart, $rangeEnd]))
            ->selectRaw("
                COUNT(*) AS total_trips,
                COALESCE(SUM(CASE WHEN trip_status = 'completed' THEN trip_amount ELSE 0 END), 0) AS completed_amount,
                COALESCE(SUM(trip_status IN ('pending','accepted','confirmed','processing','ongoing')), 0) AS ongoing_count,
                COALESCE(SUM(trip_status IN ('failed','canceled')), 0) AS canceled_count,
                COALESCE(SUM(trip_status = 'completed'), 0) AS completed_count
            ")->first();

        $total_trips = (int) ($totals->total_trips ?? 0);
        $total_trip_amount = (float) ($totals->completed_amount ?? 0);
        $total_ongoing = (int) ($totals->ongoing_count ?? 0);
        $total_canceled = (int) ($totals->canceled_count ?? 0);
        $total_completed = (int) ($totals->completed_count ?? 0);

        $vehicles = Vehicle::applyDateFilter($filter)->count();

        $monthly_trip = [];
        switch ($filter) {
            case "all_time":
                $monthly_trip = Trips::select(
                    DB::raw("(sum(trip_amount)) as trip_amount"),
                    DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                )
                    ->Completed()
                    ->groupBy(DB::raw("DATE_FORMAT(schedule_at, '%Y')"))
                    ->get()->toArray();

                $label = array_map(function ($trip) {
                    return $trip['year'];
                }, $monthly_trip);
                $data = array_map(function ($trip) {
                    return $trip['trip_amount'];
                }, $monthly_trip);
                break;
            case "this_year":
                for ($i = 1; $i <= 12; $i++) {
                    $monthly_trip[$i] = Trips::Completed()->whereMonth('schedule_at', $i)->whereYear('schedule_at', now()->format('Y'))
                        ->sum('trip_amount');
                }
                $label = $months;
                $data = $monthly_trip;
                break;
            case "previous_year":
                for ($i = 1; $i <= 12; $i++) {
                    $monthly_trip[$i] = Trips::Completed()->whereMonth('schedule_at', $i)->whereYear('schedule_at', date('Y') - 1)
                        ->sum('trip_amount');
                }
                $label = $months;
                $data = $monthly_trip;
                break;
            case "this_week":
                $weekStartDate = now()->startOfWeek();
                for ($i = 1; $i <= 7; $i++) {
                    $monthly_trip[$i] = Trips::Completed()->whereDay('schedule_at', $weekStartDate->format('d'))->whereMonth('schedule_at', now()->format('m'))
                        ->sum('trip_amount');

                    $weekStartDate = $weekStartDate->addDays(1);
                }
                $label = $days;
                $data = $monthly_trip;
                break;
            case "this_month":
                $start = now()->startOfMonth();
                $end = now()->startOfMonth()->addDays(6);
                $total_day = now()->daysInMonth;
                $remaining_days = now()->daysInMonth - 28;
                $weeks = array(
                    '"'.translate('Day').' 1-7"',
                    '"'.translate('Day').' 8-14"',
                    '"'.translate('Day').' 15-21"',
                    '"'.translate('Day').' 22-' . $total_day . '"',
                );
                for ($i = 1; $i <= 4; $i++) {
                    $monthly_trip[$i] = Trips::Completed()
                        ->whereBetween('schedule_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                        ->sum('trip_amount');
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remaining_days) : $end->addDays(7);
                }
                $label = $weeks;
                $data = $monthly_trip;
                break;
            default:
                for ($i = 1; $i <= 12; $i++) {
                    $monthly_trip[$i] = Trips::Completed()->whereMonth('schedule_at', $i)->whereYear('schedule_at', now()->format('Y'))
                        ->sum('trip_amount');
                }
                $label = $months;
                $data = $monthly_trip;
        }

        return view('rental::admin.report.provider-summary-report', compact('providers', 'new_providers', 'total_trips', 'trip_payment_methods', 'vehicles', 'monthly_trip', 'label', 'data', 'filter', 'total_trip_amount', 'total_ongoing', 'total_canceled', 'total_completed'));
    }
    public function providerSummaryExport(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $filter = $request->query('filter', 'all_time');

        $providers = Store::with('trips')->withCount('trips')
            ->whereHas('vendor',function($query){
                $query->where('status',1);
            })
            ->withModuleType('rental')
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->with([
                    'trips' => function ($query) {
                        $query->whereYear('schedule_at', now()->format('Y'));
                    },
                ]);
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->with([
                    'trips' => function ($query) {
                        $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
                    },
                ]);
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->with([
                    'trips' => function ($query) {
                        $query->whereYear('schedule_at', date('Y') - 1);
                    },
                ]);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->with([
                    'trips' => function ($query) {
                        $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
                    },
                ]);
            })
            ->when(isset($filter) && $filter == 'all_time', function ($query) {
                return $query->with([
                    'trips' => function ($query) {
                        $query;
                    },
                ]);
            })
            ->orderBy('trips_count', 'DESC')->get();

        $new_providers = Store::withModuleType('rental')->whereHas('vendor',function($query){
                $query->where('status',1);
            })
            ->applyDateFilter($filter)->count();

        $trip_payment_methods = Trips::when(isset($filter) && $filter == 'this_year', function ($query) {
            return $query->whereYear('schedule_at', now()->format('Y'));
        })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->Completed()
            ->selectRaw(DB::raw("sum(`trip_amount`) as total_trip_amount, count(*) as trip_count, IF((`payment_method`='cash_payment'), `payment_method`, IF(`payment_method`='wallet',`payment_method`, 'digital_payment')) as 'payment_methods'"))->groupBy('payment_methods')
            ->get();

        $trips = Trips::when(isset($filter) && $filter == 'this_year', function ($query) {
            return $query->whereYear('schedule_at', now()->format('Y'));
        })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })->get();
        $total_trip_amount = $trips->whereIn('trip_status', ['completed'])->sum('trip_amount');
        $total_ongoing = $trips->whereIn('trip_status', ['pending', 'accepted', 'confirmed', 'processing', 'ongoing'])->count();
        $total_canceled = $trips->whereIn('trip_status', ['failed', 'canceled'])->count();
        $total_completed = $trips->whereIn('trip_status', ['completed'])->count();

        $data = [
            'providers'=>$providers,
            'search'=>$request->search??null,
            'new_providers'=>$new_providers,
            'trips'=>$trips->count(),
            'total_trip_amount'=>$total_trip_amount,
            'total_ongoing'=>$total_ongoing,
            'total_canceled'=>$total_canceled,
            'total_completed'=>$total_completed,
            'cash_payments'=>count($trip_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($trip_payment_methods[0])?$trip_payment_methods[0]->total_trip_amount:0):0,
            'digital_payments'=>count($trip_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($trip_payment_methods[1])?$trip_payment_methods[1]->total_trip_amount:0):0,
            'wallet_payments'=>count($trip_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($trip_payment_methods[2])?$trip_payment_methods[2]->total_trip_amount:0):0,
            'filter'=>$filter,
        ];
        if ($request->type == 'excel') {
            return Excel::download(new ProviderSummaryReportExport($data), 'ProviderSummaryReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new ProviderSummaryReportExport($data), 'ProviderSummaryReport.csv');
        }
    }
    public function providerSalesReport(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

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
            '"Sun"',
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"'
        );
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;



        // Called once, not twice: it builds both result sets, and the second call repeated
        // every aggregate.
        $salesData = $this->get_provider_sales_data($request);
        $vehicles = $salesData['vehicles']->paginate(config('default_pagination'))->withQueryString();
        $trip_totals = $salesData['trip_totals'];

        $monthly_trip = [];
        $label = [];
        if ($filter != 'custom') {
            switch ($filter) {
                case "all_time":
                    $monthly_trip = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })->select(
                            DB::raw("(sum(trip_amount)) as trip_amount"),
                            DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                        )
                        ->groupBy(DB::raw("DATE_FORMAT(schedule_at, '%Y')"))
                        ->get()->toArray();

                    $label = array_map(function ($trip) {
                        return $trip['year'];
                    }, $monthly_trip);
                    $data = array_map(function ($trip) {
                        return $trip['trip_amount'];
                    }, $monthly_trip);
                    break;
                case "this_year":
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_trip[$i] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })->whereMonth('schedule_at', $i)->whereYear('schedule_at', now()->format('Y'))
                            ->sum('trip_amount');
                    }
                    $label = $months;
                    $data = $monthly_trip;
                    break;
                case "previous_year":
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_trip[$i] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })->whereMonth('schedule_at', $i)->whereYear('schedule_at', date('Y') - 1)
                            ->sum('trip_amount');
                    }
                    $label = $months;
                    $data = $monthly_trip;
                    break;
                case "this_week":
                    $weekStartDate = now()->startOfWeek();
                    for ($i = 1; $i <= 7; $i++) {
                        $monthly_trip[$i] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })->whereDay('schedule_at', $weekStartDate->format('d'))->whereMonth('schedule_at', now()->format('m'))
                            ->sum('trip_amount');
                        $weekStartDate = $weekStartDate->addDays(1);
                    }
                    $label = $days;
                    $data = $monthly_trip;
                    break;
                case "this_month":
                    $start = now()->startOfMonth();
                    $end = now()->startOfMonth()->addDays(6);
                    $total_day = now()->daysInMonth;
                    $remaining_days = now()->daysInMonth - 28;
                    $weeks = array(
                        '"'.translate('Day').' 1-7"',
                        '"'.translate('Day').' 8-14"',
                        '"'.translate('Day').' 15-21"',
                        '"'.translate('Day').' 22-' . $total_day . '"',
                    );
                    for ($i = 1; $i <= 4; $i++) {
                        $monthly_trip[$i] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })
                            ->whereBetween('schedule_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                            ->sum('trip_amount');
                        $start = $start->addDays(7);
                        $end = $i == 3 ? $end->addDays(7 + $remaining_days) : $end->addDays(7);
                    }
                    $label = $weeks;
                    $data = $monthly_trip;
                    break;
                default:
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_trip[$i] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })->whereMonth('schedule_at', $i)->whereYear('schedule_at', now()->format('Y'))
                            ->sum('trip_amount');
                    }
                    $label = $months;
                    $data = $monthly_trip;
            }
        } else {

            $to = Carbon::parse($to);
            $from = Carbon::parse($from);

            $years_count = (int) $from->diffInYears($to);
            $months_count = (int) $from->diffInMonths($to);
            $weeks_count = (int) $from->diffInWeeks($to);
            $days_count = (int) $from->diffInDays($to);


            if ($years_count > 0) {
                $monthly_trip = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                    return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                })
                    ->when(isset($provider), function ($query) use ($provider) {
                        return $query->where('provider_id', $provider->id);
                    })
                    ->whereBetween('schedule_at', ["{$from}", "{$to->format('Y-m-d')} 23:59:59"])
                    ->select(
                        DB::raw("(sum(trip_amount)) as trip_amount"),
                        DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                    )
                    ->groupBy('year')
                    ->get()->toArray();

                $label = array_map(function ($trip) {
                    return $trip['year'];
                }, $monthly_trip);
                $data = array_map(function ($trip) {
                    return $trip['trip_amount'];
                }, $monthly_trip);
            } elseif ($months_count > 0) {
                for ($i = (int)$from->format('m'); $i <= (int)$from->format('m') + $months_count; $i++) {
                    $monthly_trip[$i] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })->whereMonth('schedule_at', $i)
                        ->sum('trip_amount');
                    $label[$i] = $months[$i - 1];
                }
                $label = $label;
                $data = $monthly_trip;
            } elseif ($weeks_count > 0) {
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_trip[$key] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })->whereBetween('schedule_at', ["{$key} 00:00:00", "{$key} 23:59:59"])
                        ->sum('trip_amount');
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_trip;
            } elseif ($days_count >= 0) {
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_trip[$key] = Trips::Completed()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })->whereBetween('schedule_at', ["{$key} 00:00:00", "{$key} 23:59:59"])
                        ->sum('trip_amount');
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_trip;
            }
        }

        return view('rental::admin.report.provider-sales-report', compact('zone', 'provider', 'vehicles', 'trip_totals', 'data', 'label', 'filter'));
    }
    public function providerSalesExport(Request $request)
    {
        $from = session('from_date');
        $to = session('to_date');
        $filter = $request->query('filter', 'all_time');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');


        // One call, not two: the same builder was assembled twice for one export, and the second
        // asked for a `trips` key this data set stopped carrying when the totals became a single
        // pre-aggregated row -- which is what threw on every request.
        $salesData = $this->get_provider_sales_data($request);
        $vehicles = $salesData['vehicles']->get();

        $data = [
            'vehicles'=>$vehicles,
            'trip_totals'=>$salesData['trip_totals'],
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'provider'=>is_numeric($provider_id)?Helpers::get_stores_name($provider_id):null,
            'filter'=>$filter,
        ];
        if ($request->type == 'excel') {
            return Excel::download(new ProviderSalesReportExport($data), 'ProviderVehicleReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new ProviderSalesReportExport($data), 'ProviderVehicleReport.csv');
        }
    }
    private static function get_provider_sales_data($request){
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        [$rangeStart, $rangeEnd] = (new self)->tripDateRange($filter, $from, $to);

        $vehicles =Vehicle::withCount([
            'tripDetails as trips_count' => function ($query) use ($from, $to, $filter) {
                $query->whereHas('trip', function ($query) {
                    return $query->whereIn('trip_status', ['completed']);
                })->applyDateFilter($filter, $from, $to);
            }, 'vehicleIdentities'
        ] )
            ->withSum([
                'tripDetails' => function ($query) use ($from, $to, $filter) {
                    $query->whereHas('trip', function ($query) {
                        return $query->whereIn('trip_status', ['completed']);
                    })->applyDateFilter($filter, $from, $to);
                },
            ], 'quantity')
            ->addSelect([
                'total_discount' => function ($query) use ($from, $to, $filter) {
                    $query->selectRaw('SUM(trip_details.discount_on_trip * trip_details.quantity)')
                        ->from('trip_details')
                        ->join('trips', 'trips.id', '=', 'trip_details.trip_id')
                        ->whereColumn('trip_details.vehicle_id', 'vehicles.id')
                        ->whereIn('trips.trip_status', ['completed'])
                        ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                            return $query->whereBetween('trip_details.created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
                        })
                        ->when(isset($filter) && $filter == 'this_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'this_month', function ($query) {
                            return $query->whereMonth('trip_details.created_at', now()->format('m'))
                                ->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', date('Y') - 1);
                        })
                        ->when(isset($filter) && $filter == 'this_week', function ($query) {
                            return $query->whereBetween('trip_details.created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
                        });
                },
                'trips_sum_price' => function ($query) use ($from, $to, $filter) {
                    $query->selectRaw('SUM(trip_details.price * trip_details.quantity)')
                        ->from('trip_details')
                        ->join('trips', 'trips.id', '=', 'trip_details.trip_id')
                        ->whereColumn('trip_details.vehicle_id', 'vehicles.id')
                        ->whereIn('trips.trip_status', ['completed'])
                        ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                            return $query->whereBetween('trip_details.created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
                        })
                        ->when(isset($filter) && $filter == 'this_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'this_month', function ($query) {
                            return $query->whereMonth('trip_details.created_at', now()->format('m'))
                                ->whereYear('trip_details.created_at', now()->format('Y'));
                        })
                        ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                            return $query->whereYear('trip_details.created_at', date('Y') - 1);
                        })
                        ->when(isset($filter) && $filter == 'this_week', function ($query) {
                            return $query->whereBetween('trip_details.created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
                        });
                },
            ])
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('provider_id', $zone->stores->pluck('id'));
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->having('trips_count', '>' ,0)
            ->orderBy('trips_count', 'desc');

        // One aggregate row, not ->get(). This hydrated every completed trip -- 1.4M of them
        // -- each with its trip_transaction and three correlated withSum() subqueries, to
        // produce the six scalars the view actually reads. It crashed the worker outright.
        //
        // trip_transaction is a hasOne and no trip has more than one row, so the left join
        // cannot fan out and duplicate trip_amount.
        $tripTotals = Trips::whereNotIn('trip_status', ['failed', 'canceled'])
            ->Completed()
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('provider_id', Store::withoutGlobalScopes()->where('zone_id', $zone->id)->select('id'));
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when($rangeStart && $rangeEnd, function ($query) use ($rangeStart, $rangeEnd) {
                return $query->whereBetween('schedule_at', [$rangeStart, $rangeEnd]);
            })
            ->leftJoin('trip_transactions', 'trip_transactions.trip_id', '=', 'trips.id')
            ->toBase()
            ->selectRaw('
                COUNT(*) AS trips_count,
                COALESCE(SUM(trips.trip_amount), 0) AS trip_amount,
                COALESCE(SUM(trips.tax_amount), 0) AS tax_amount,
                COALESCE(SUM(trip_transactions.admin_commission), 0) AS admin_commission,
                COALESCE(SUM(trip_transactions.admin_expense), 0) AS admin_expense,
                COALESCE(SUM(trip_transactions.store_amount), 0) AS store_amount
            ')
            ->first();

        return ['vehicles' => $vehicles, 'trip_totals' => $tripTotals];
    }
    public function providerTripReport(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

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
            '"Sun"',
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"'
        );

        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;

        $rentalModuleId = Module::where('module_type', 'rental')->first()->id;

        $trips = Trips::with(['customer', 'provider', 'proDiscount'])
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('provider_id', $zone->stores->pluck('id'));
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('schedule_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->withSum('trip_transaction', 'admin_commission')
            ->withSum('trip_transaction', 'admin_expense')
            ->orderBy('schedule_at', 'desc')->paginate(config('default_pagination'));

        // One aggregate row instead of hydrating every matching trip with three relations and
        // reducing the collection in PHP eleven times. Against 2M trips that crashed the
        // worker. proDiscount is a hasOne, so the left join cannot duplicate a trip.
        [$rangeStart, $rangeEnd] = $this->tripDateRange($filter, $from, $to);

        $ongoingStatuses = ['pending', 'accepted', 'confirmed', 'processing', 'ongoing'];
        $canceledStatuses = ['failed', 'canceled'];
        $ongoingList = "'".implode("','", $ongoingStatuses)."'";
        $canceledList = "'".implode("','", $canceledStatuses)."'";

        $tripTotals = Trips::when(isset($zone), function ($query) use ($zone) {
            return $query->whereIn('provider_id', Store::withoutGlobalScopes()->where('zone_id', $zone->id)->select('id'));
        })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when($rangeStart && $rangeEnd, function ($query) use ($rangeStart, $rangeEnd) {
                return $query->whereBetween('schedule_at', [$rangeStart, $rangeEnd]);
            })
            ->leftJoin('order_pro_discounts', 'order_pro_discounts.trip_id', '=', 'trips.id')
            ->toBase()
            ->selectRaw("
                COUNT(*) AS trips_count,
                COALESCE(SUM(trips.trip_amount), 0) AS total_trip_amount,
                COALESCE(SUM(trips.coupon_discount_amount), 0) AS total_coupon_discount,
                COALESCE(SUM(trips.discount_on_trip), 0) AS total_product_discount,
                COALESCE(SUM(order_pro_discounts.amount_saved), 0) AS total_pro_discount,
                COALESCE(SUM(CASE WHEN trips.trip_status IN ({$ongoingList}) THEN trips.trip_amount ELSE 0 END), 0) AS total_ongoing,
                COALESCE(SUM(CASE WHEN trips.trip_status IN ({$canceledList}) THEN trips.trip_amount ELSE 0 END), 0) AS total_canceled,
                COALESCE(SUM(CASE WHEN trips.trip_status = 'completed' THEN trips.trip_amount ELSE 0 END), 0) AS total_completed,
                COALESCE(SUM(trips.trip_status IN ({$ongoingList})), 0) AS total_ongoing_count,
                COALESCE(SUM(trips.trip_status IN ({$canceledList})), 0) AS total_canceled_count,
                COALESCE(SUM(trips.trip_status = 'completed'), 0) AS total_completed_count
            ")
            ->first();

        $trips_count = (int) ($tripTotals->trips_count ?? 0);
        $total_trip_amount = (float) ($tripTotals->total_trip_amount ?? 0);
        $total_coupon_discount = (float) ($tripTotals->total_coupon_discount ?? 0);
        $total_product_discount = (float) ($tripTotals->total_product_discount ?? 0);
        $total_pro_discount = (float) ($tripTotals->total_pro_discount ?? 0);
        $total_ongoing = (float) ($tripTotals->total_ongoing ?? 0);
        $total_canceled = (float) ($tripTotals->total_canceled ?? 0);
        $total_completed = (float) ($tripTotals->total_completed ?? 0);
        $total_ongoing_count = (int) ($tripTotals->total_ongoing_count ?? 0);
        $total_canceled_count = (int) ($tripTotals->total_canceled_count ?? 0);
        $total_completed_count = (int) ($tripTotals->total_completed_count ?? 0);

        $trip_payment_methods = Trips::when(isset($zone), function ($query) use ($zone) {
            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
        })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('schedule_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->selectRaw(DB::raw("sum(`trip_amount`) as total_trip_amount, count(*) as trip_count, IF((`payment_method`='cash_payment'), `payment_method`, IF(`payment_method`='wallet',`payment_method`, 'digital_payment')) as 'payment_methods'"))
            ->groupBy('payment_methods')
            ->get();

        $monthly_trip = [];
        $label = [];
        if ($filter != 'custom') {
            switch ($filter) {
                case "all_time":
                    $monthly_trip = Trips::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })
                        ->select(
                            DB::raw("(sum(trip_amount)) as trip_amount"),
                            DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                        )
                        ->groupBy(DB::raw("DATE_FORMAT(schedule_at, '%Y')"))
                        ->get()->toArray();

                    $label = array_map(function ($trip) {
                        return $trip['year'];
                    }, $monthly_trip);
                    $data = array_map(function ($trip) {
                        return $trip['trip_amount'];
                    }, $monthly_trip);
                    break;
                case "this_year":
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_trip[$i] = Trips::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })
                            ->whereMonth('schedule_at', $i)->whereYear('schedule_at', now()->format('Y'))
                            ->sum('trip_amount');
                    }
                    $label = $months;
                    $data = $monthly_trip;
                    break;
                case "previous_year":
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_trip[$i] = Trips::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })
                            ->whereMonth('schedule_at', $i)->whereYear('schedule_at', date('Y') - 1)
                            ->sum('trip_amount');
                    }
                    $label = $months;
                    $data = $monthly_trip;
                    break;
                case "this_week":
                    $weekStartDate = now()->startOfWeek();
                    for ($i = 1; $i <= 7; $i++) {
                        $monthly_trip[$i] = Trips::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })->whereDay('schedule_at', $weekStartDate->format('d'))->whereMonth('schedule_at', now()->format('m'))
                            ->sum('trip_amount');
                        $weekStartDate = $weekStartDate->addDays(1);
                    }
                    $label = $days;
                    $data = $monthly_trip;
                    break;
                case "this_month":
                    $start = now()->startOfMonth();
                    $end = now()->startOfMonth()->addDays(6);
                    $total_day = now()->daysInMonth;
                    $remaining_days = now()->daysInMonth - 28;
                    $weeks = array(
                        '"'.translate('Day').' 1-7"',
                        '"'.translate('Day').' 8-14"',
                        '"'.translate('Day').' 15-21"',
                        '"'.translate('Day').' 22-' . $total_day . '"',
                    );
                    for ($i = 1; $i <= 4; $i++) {
                        $monthly_trip[$i] = Trips::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })
                            ->whereBetween('schedule_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                            ->sum('trip_amount');
                        $start = $start->addDays(7);
                        $end = $i == 3 ? $end->addDays(7 + $remaining_days) : $end->addDays(7);
                    }
                    $label = $weeks;
                    $data = $monthly_trip;
                    break;
                default:
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_trip[$i] = Trips::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($provider), function ($query) use ($provider) {
                                return $query->where('provider_id', $provider->id);
                            })->whereMonth('schedule_at', $i)->whereYear('schedule_at', now()->format('Y'))
                            ->sum('trip_amount');
                    }
                    $label = $months;
                    $data = $monthly_trip;
            }
        } else {

            $to = Carbon::parse($to);
            $from = Carbon::parse($from);

            $years_count = (int) $from->diffInYears($to);
            $months_count = (int) $from->diffInMonths($to);
            $weeks_count = (int) $from->diffInWeeks($to);
            $days_count = (int) $from->diffInDays($to);



            if ($years_count > 0) {
                $monthly_trip = Trips::when(isset($zone), function ($query) use ($zone) {
                    return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                })
                    ->when(isset($provider), function ($query) use ($provider) {
                        return $query->where('provider_id', $provider->id);
                    })
                    ->whereBetween('schedule_at', ["{$from}", "{$to->format('Y-m-d')} 23:59:59"])
                    ->select(
                        DB::raw("(sum(trip_amount)) as trip_amount"),
                        DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                    )
                    ->groupBy('year')
                    ->get()->toArray();

                $label = array_map(function ($trip) {
                    return $trip['year'];
                }, $monthly_trip);
                $data = array_map(function ($trip) {
                    return $trip['trip_amount'];
                }, $monthly_trip);
            } elseif ($months_count > 0) {
                for ($i = (int)$from->format('m'); $i <= (int)$from->format('m') + $months_count; $i++) {
                    $monthly_trip[$i] = Trips::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })
                        ->whereMonth('schedule_at', $i)
                        ->sum('trip_amount');
                    $label[$i] = $months[$i - 1];
                }
                $label = $label;
                $data = $monthly_trip;
            } elseif ($weeks_count > 0) {

                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_trip[$key] = Trips::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })
                        ->whereBetween('schedule_at', ["{$key} 00:00:00", "{$key} 23:59:59"])
                        ->sum('trip_amount');
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_trip;
            } elseif ($days_count >= 0) {
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_trip[$key] = Trips::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('provider_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($provider), function ($query) use ($provider) {
                            return $query->where('provider_id', $provider->id);
                        })
                        ->whereBetween('schedule_at', ["{$key} 00:00:00", "{$key} 23:59:59"])
                        ->sum('trip_amount');
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_trip;
            }
        }


        return view('rental::admin.report.provider-trip-report', compact('zone', 'provider', 'trips', 'trips_count', 'monthly_trip', 'total_trip_amount', 'trip_payment_methods', 'total_coupon_discount', 'total_product_discount', 'total_pro_discount', 'label', 'data', 'filter', 'total_ongoing', 'total_canceled', 'total_completed', 'total_ongoing_count', 'total_canceled_count', 'total_completed_count', 'rentalModuleId'));
    }
    public function providerTripExport(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $provider_id = $request->query('provider_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $provider = is_numeric($provider_id) ? Store::findOrFail($provider_id) : null;
        $filter = $request->query('filter', 'all_time');

        $trips = Trips::with(['customer', 'provider', 'proDiscount'])
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('provider_id', $zone->stores->pluck('id'));
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('schedule_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->withSum('trip_transaction', 'admin_commission')
            ->withSum('trip_transaction', 'admin_expense')
            ->orderBy('schedule_at', 'desc')->get();

        $trips_list = Trips::with(['customer', 'provider', 'proDiscount'])
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('provider_id', $zone->stores->pluck('id'));
            })
            ->when(isset($provider), function ($query) use ($provider) {
                return $query->where('provider_id', $provider->id);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('schedule_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('schedule_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->withSum('trip_transaction', 'admin_commission')
            ->withSum('trip_transaction', 'admin_expense')
            ->orderBy('schedule_at', 'desc')->get();

        $total_trip_amount = $trips_list->sum('trip_amount');
        $total_ongoing_count = $trips_list->whereIn('trip_status', ['pending', 'accepted', 'confirmed', 'processing', 'ongoing'])->count();
        $total_canceled_count = $trips_list->whereIn('trip_status', ['failed', 'canceled'])->count();
        $total_completed_count = $trips_list->where('trip_status', 'completed')->count();


        $data = [
            'trips'=>$trips,
            'total_trips'=>$trips->count(),
            'total_trip_amount'=>$total_trip_amount,
            'total_ongoing_count'=>$total_ongoing_count,
            'total_canceled_count'=>$total_canceled_count,
            'total_completed_count'=>$total_completed_count,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'provider'=>is_numeric($provider_id)?Helpers::get_stores_name($provider_id):null,
            'filter'=>$filter,
        ];
        if ($request->type == 'excel') {
            return Excel::download(new ProviderTripReportExport($data), 'ProviderTripReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new ProviderTripReportExport($data), 'ProviderTripReport.csv');
        }
    }
    public function generateStatement($id)
    {
        $company_phone = Helpers::get_business_settings('phone', false);
        $company_email = Helpers::get_business_settings('email_address', false);
        $company_name = Helpers::get_business_settings('business_name', false);
        $company_web_logo = Helpers::get_full_url(
            'business',
            Helpers::get_business_settings('logo', false),
            app(BusinessSettingService::class)->findStorageDisk('logo')
        );
        $footer_text =Helpers::get_business_settings('footer_text', false);

        $trip_transaction = TripTransaction::with('trip', 'trip.trip_details', 'trip.customer', 'trip.provider', 'trip.proDiscount')->where('id', $id)->first();
        $data["email"] = $trip_transaction->trip->customer != null ? $trip_transaction->trip->customer["email"] : translate('email_not_found');
        $data["client_name"] = $trip_transaction->trip->customer != null ? $trip_transaction->trip->customer["f_name"] . ' ' . $trip_transaction->trip->customer["l_name"] : translate('No data found');
        $data["trip_transaction"] = $trip_transaction;
        $mpdf_view = View::make(
            'rental::admin.report.trip-transaction-statement',
            compact('trip_transaction', 'company_phone', 'company_name', 'company_email', 'company_web_logo', 'footer_text')
        );
        Helpers::gen_mpdf($mpdf_view, 'trip_trans_statement', $trip_transaction->id);
    }

    public function tripInvoice($id)
    {
        $id = base64_decode($id);
        $BusinessData = ['footer_text', 'email_address'];
        $trip = Trips::with('proDiscount')->findOrFail($id);
        $BusinessData = Helpers::get_business_settings_many($BusinessData);
        $logo = BusinessSetting::where('key', "logo")->first();
        $mpdf_view = View::make('email-templates.pdf-rental.trip-invoice', compact('trip', 'BusinessData', 'logo'));
        Helpers::gen_mpdf(view: $mpdf_view, file_prefix: 'TripInvoice', file_postfix: $id);
        return back();
    }

}

