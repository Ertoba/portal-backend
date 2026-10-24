<?php

namespace Modules\Rental\Http\Controllers\Web\Provider;

use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Trips;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionTransaction;
use Modules\Rental\Entities\TripTransaction;


class ProviderDashBoardController extends Controller
{

    public function providerDashboard(Request $request)
    {
        $deliveryStatistics= $this->getTripData($request);
        $data = self::dashboard_data($request);
        $commission = $data['commission'];
        $label = $data['label'];
        return view("rental::provider.dashboard.dashboard", [
            'pendingCount' => $deliveryStatistics['pendingCount'],
            'confirmedCount' => $deliveryStatistics['confirmedCount'],
            'ongoingCount' => $deliveryStatistics['ongoingCount'],
            'completedCount' => $deliveryStatistics['completedCount'],
            'canceledCount' => $deliveryStatistics['canceledCount'],
            'totalCount' => $deliveryStatistics['totalCount'],
            'scheduledCount' => $deliveryStatistics['scheduledCount'],
            'instantCount' => $deliveryStatistics['instantCount'],
            'commission' => $commission,
            'label' => $label,
        ]);

    }


    public function deliveryStatistics(Request $request)
    {
        $data= $this->getTripData($request);
        return response()->json([
            'delivery_statistics' => view('rental::provider.dashboard._delivery-statistics', [
                'pendingCount' => $data['pendingCount'],
                'confirmedCount' => $data['confirmedCount'],
                'ongoingCount' => $data['ongoingCount'],
                'completedCount' => $data['completedCount'],
                'canceledCount' => $data['canceledCount'],
                'totalCount' => $data['totalCount'],
                'scheduledCount' => $data['scheduledCount'],
                'instantCount' => $data['instantCount'],
                ])->render(),
        ], 200);
    }


    private function getTripData($request){
        $statistics_type = $request->input('statistics_type', 'all');

        $tripQuery = Trips::where('provider_id',Helpers::get_store_id());
        if ($statistics_type == 'this_year') {
            $tripQuery->whereYear('created_at', now()->year);
        } elseif ($statistics_type == 'this_month') {
            $tripQuery->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($statistics_type == 'this_week') {
            $tripQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        }
        return [
            'pendingCount' => (clone $tripQuery)->pending()->count(),
            'confirmedCount' => (clone $tripQuery)->confirmed()->count(),
            'ongoingCount' =>  (clone $tripQuery)->ongoing()->count(),
            'completedCount' => (clone $tripQuery)->completed()->count(),
            'canceledCount' => (clone $tripQuery)->canceled()->count(),
            'scheduledCount' => (clone $tripQuery)->scheduled()->count(),
            'instantCount' => (clone $tripQuery)->instant()->count(),
            'totalCount' => (clone $tripQuery)->count(),
        ];
    }



    public function commissionOverview(Request $request)
    {
        $request->input('commission_overview', 'all');
        $data = self::dashboard_data($request);
        $commission = $data['commission'];
        $label = $data['label'];
        $grossEarning = collect($commission)->sum();

        return response()->json([
            'view' => view('rental::provider.dashboard._sale-chart', compact('commission', 'label', 'grossEarning'))->render(),
            'grossEarning' => $grossEarning,
            'commission' => array_map(function($val) {
                    return number_format((float)$val, 2, '.', '');
                }, array_values($commission)),
            'labels' => array_map(function($val) {
                return trim($val, '"');
            }, $label),
        ], 200);
    }

    public function dashboard_data($request)
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
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"',
            '"Sun"',
        );
        $commission = [];

        switch ($request['commission_overview']) {
            case "this_year":
                $commission = $this->commissionByMonth();
                $label = $months;

                break;

            case "this_week":
                $weekStartDate = now()->startOfWeek();

                // One grouped query for the week instead of seven dated sums. The keys are
                // still 0..6 in week order, and a day with no transactions still yields 0.
                $weekly = TripTransaction::where('provider_id', Helpers::get_store_id())
                    ->whereBetween('created_at', [
                        $weekStartDate->copy()->startOfDay(),
                        $weekStartDate->copy()->addDays(6)->endOfDay(),
                    ])
                    ->groupBy('bucket')
                    ->selectRaw('DATE(created_at) as bucket, SUM(store_amount - tax) as aggregate')
                    ->pluck('aggregate', 'bucket');

                for ($i = 0; $i < 7; $i++) {
                    $currentDate = $weekStartDate->copy()->addDays($i);

                    $commission[$i] = $weekly[$currentDate->format('Y-m-d')] ?? 0;
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

                for ($i = 1; $i <= 4; $i++) {
                    $end = $start->copy()->addDays(6);

                    if ($i == 4) {
                        $end = now()->endOfMonth();
                    }


                    $commission[$i] = TripTransaction::where('provider_id',Helpers::get_store_id())
                        ->whereBetween('created_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                        ->sum(DB::raw('store_amount - tax'));

                    $start = $end->copy()->addDay();
                }

                $label = $weeks;

                break;

            default:
                $commission = $this->commissionByMonth();
                $label = $months;
        }


        $dash_data['commission'] = $commission;
        $dash_data['label'] = $label;

        return $dash_data;
    }

    /**
     * Commission per calendar month of the current year, keyed 1..12.
     *
     * This was twelve separate SUM queries in a loop — and the dashboard runs the loop
     * twice (once for the page, once for the chart endpoint), so a provider dashboard
     * issued 24 of them. One grouped query returns the same numbers; months with no
     * transactions still come back as 0, exactly as sum() on an empty set did.
     */
    private function commissionByMonth(): array
    {
        $sums = TripTransaction::where('provider_id', Helpers::get_store_id())
            ->whereYear('created_at', now()->format('Y'))
            ->groupBy('bucket')
            ->selectRaw('MONTH(created_at) as bucket, SUM(store_amount - tax) as aggregate')
            ->pluck('aggregate', 'bucket');

        $commission = [];
        for ($i = 1; $i <= 12; $i++) {
            $commission[$i] = $sums[$i] ?? 0;
        }

        return $commission;
    }

}
