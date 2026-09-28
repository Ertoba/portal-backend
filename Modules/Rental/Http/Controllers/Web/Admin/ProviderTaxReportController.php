<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;

use Modules\Rental\Exports\ProviderTaxExport;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Exports\ProviderWiseTaxExport;
use NunoMaduro\Collision\Provider;

class ProviderTaxReportController extends Controller
{

        public function __construct()
    {
        DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
    }


    public function providerWiseTaxes(Request $request)
    {

        $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        $key = explode(' ', $request['search'] ?? '');

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        $store_id = $request->query('provider_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;

        // $start = microtime(true);

        $data = $this->providerWiseTaxData($store, $startDate, $endDate, $key);
        $result = $data['result'];

        $totalOrders = $result->total_orders;
        $totalOrderAmount = $result->total_order_amount;
        $totalTax = $result->total_tax;

        $storeQuery = $data['storeQuery'];
        $storeQuery =  $storeQuery->paginate(config('default_pagination'))->withQueryString();
        $storeIds = $storeQuery->pluck('store_id')->toArray();

        $stores = $this->getOrderTaxData($startDate, $endDate, $storeIds, $storeQuery);
        // $time = microtime(true) - $start;
        // dd("Query took {$time} seconds", $stores);
        $startDate = Carbon::parse($startDate)->toIso8601String();
        $endDate = Carbon::parse($endDate)->toIso8601String();
        return view('rental::admin.report.tax-report.provider-tax-report', compact('totalOrders', 'totalOrderAmount', 'totalTax', 'store', 'stores', 'dateRange', 'startDate', 'endDate'));
    }





    private function  providerWiseTaxData($store, $startDate, $endDate, $search)
    {
        $query = DB::table('trips')
            ->selectRaw('COUNT(*) as total_orders,
                        SUM(trip_amount) as total_order_amount,
                        SUM(tax_amount) as total_tax')
            ->whereIn('trip_status', ['completed', 'refund_requested', 'refund_request_canceled']);

        if (isset($store)) {
            $query->where('provider_id', $store->id);
        }

        if (isset($startDate) && isset($endDate)) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        if (isset($search)) {
            $query->whereExists(function ($subQuery) use ($search) {
                $subQuery->select(DB::raw(1))
                    ->from('stores')
                    ->whereRaw('stores.id = trips.provider_id')
                    ->where(function ($q) use ($search) {
                        foreach ($search as $value) {
                            $q->orWhere('stores.name', 'like', "%{$value}%");
                        }
                    });
            });
        }

        $result = $query->first();

        $storeQuery = DB::table('stores as stores')
            ->selectRaw(' stores.id as store_id,
                            stores.name as store_name,
                            stores.phone as store_phone,
                            COUNT(DISTINCT trips.id) as total_orders,
                            SUM(trips.trip_amount) as total_order_amount,
                            SUM(trips.tax_amount) as total_tax_amount')
            ->join('trips as trips', function ($join) use ($startDate, $endDate) {
                $join->on('trips.provider_id', '=', 'stores.id')
                    ->whereIn('trips.trip_status', ['completed', 'refund_requested', 'refund_request_canceled']);

                if ($startDate && $endDate) {
                    $join->whereBetween('trips.created_at', [$startDate, $endDate]);
                }
            })
            ->when(isset($store), fn($query) => $query->where('stores.id', $store->id))
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    foreach ($search as $searchTerm) {
                        $q->orWhere('stores.name', 'like', "%{$searchTerm}%");
                    }
                });
            })->groupBy('stores.id');
        return [
            'result' => $result,
            'storeQuery' => $storeQuery,
        ];
    }


    private function getOrderTaxData($startDate, $endDate, $storeIds, $storeQuery, $export = false)
    {
        $taxGrouped = [];
        $taxQuery = DB::table('order_taxes as order_taxes')
            ->selectRaw('trips.provider_id, order_taxes.tax_name, SUM(order_taxes.tax_amount) as total_tax_amount')
            ->join('trips', 'order_taxes.order_id', '=', 'trips.id')
            ->where('order_taxes.order_type', 'Modules\Rental\Entities\Trips')
            ->whereIn('trips.trip_status', ['completed', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('trips.created_at', [$startDate, $endDate]);
            })
            ->whereIn('trips.provider_id',  $storeIds)
            ->groupBy('trips.provider_id', 'order_taxes.tax_name')
            ->get();

        foreach ($taxQuery as $tax) {
            $taxGrouped[$tax->provider_id][] = [
                'tax_name' => $tax->tax_name,
                'total_tax_amount' => (float)$tax->total_tax_amount,
            ];
        }
        if ($export) {

            $stores = $storeQuery->map(function ($store) use ($taxGrouped) {
                return (object)[
                    'store_id' => $store->store_id,
                    'store_name' => $store->store_name,
                    'store_phone' => $store->store_phone,
                    'store_total_tax_amount' => $store->total_tax_amount,
                    'total_orders' => (int)$store->total_orders,
                    'total_order_amount' => (float)$store->total_order_amount,
                    'tax_data' => $taxGrouped[$store->store_id] ?? [],
                ];
            });

            return $stores;
        }
        $stores = $storeQuery->getCollection()->map(function ($store) use ($taxGrouped) {
            return (object)[
                'store_id' => $store->store_id,
                'store_name' => $store->store_name,
                'store_phone' => $store->store_phone,
                'total_orders' => (int)$store->total_orders,
                'store_total_tax_amount' => $store->total_tax_amount,
                'total_order_amount' => (float)$store->total_order_amount,
                'tax_data' => $taxGrouped[$store->store_id] ?? [],
            ];
        });


        $stores = $storeQuery->setCollection($stores);
        return $stores;
    }

    public function providerWiseTaxExport(Request $request)
    {
        $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        $key = explode(' ', $request['search'] ?? '');

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;

        // $start = microtime(true);

        $data = $this->providerWiseTaxData($store, $startDate, $endDate, $key);
        $summary = $data['result'];
        $storeQuery = $data['storeQuery'];
        $storeQuery =  $storeQuery->cursor();
        $storeIds = $storeQuery->pluck('store_id')->toArray();

        $stores = $this->getOrderTaxData($startDate, $endDate, $storeIds, $storeQuery, true);

        $startDate = Carbon::parse($startDate)->toIso8601String();
        $endDate = Carbon::parse($endDate)->toIso8601String();
        $data = [
            'stores' => $stores,
            'search' => $request->search ?? null,
            'from' => $startDate,
            'to' => $endDate,
            'summary' => $summary
        ];
        // dd($request->export_type);
        if ($request->export_type == 'excel') {
            return Excel::download(new ProviderWiseTaxExport($data), 'ProviderWiseTaxExport.xlsx');
        } else if ($request->export_type == 'csv') {
            return Excel::download(new ProviderWiseTaxExport($data), 'ProviderWiseTaxExport.csv');
        }
    }


    public function providerTax(Request $request)
    {

        $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');

        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        $store_id = $request->id;
        $store = is_numeric($store_id) ? Store::select('id', 'name', 'phone')->findOrFail($store_id) : null;

        // $start = microtime(true);
        $providertaxData =   $this->getprovidertaxData($store->id, $startDate, $endDate);
        $summary =   $providertaxData['summary'];
        $orders = $providertaxData['orders'];

        $totalOrders = $summary->total_orders;
        $totalOrderAmount = $summary->total_order_amount;
        $totalTax = $summary->total_tax;

        $orders = $orders->paginate(config('default_pagination'))
            ->withQueryString();

        // $time = microtime(true) - $start;
        // dd("Query took {$time} seconds", $stores);
        $startDate = Carbon::parse($startDate)->format('d M, Y');
        $endDate = Carbon::parse($endDate)->format('d M, Y');
        return view('rental::admin.report.tax-report.provider-tax-detail-report', compact('totalOrders', 'totalOrderAmount', 'totalTax', 'store', 'orders', 'startDate', 'endDate'));
    }

    public function providerTaxExport(Request $request)
    {
        $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        $store_id = $request->id;
        $store = is_numeric($store_id) ? Store::select('id', 'name', 'phone')->findOrFail($store_id) : null;

        // $start = microtime(true);
        $providertaxData =   $this->getprovidertaxData($store->id, $startDate, $endDate);
        $summary =   $providertaxData['summary'];
        $orders = $providertaxData['orders'];

        $orders = $orders->cursor();

        // $time = microtime(true) - $start;
        // dd("Query took {$time} seconds", $stores);
        $startDate = Carbon::parse($startDate)->format('d M, Y');
        $endDate = Carbon::parse($endDate)->format('d M, Y');

        $data = [
            'orders' => $orders,
            'search' => $request->search ?? null,
            'from' => $startDate,
            'to' => $endDate,
            'summary' => $summary
        ];
        // dd($request->export_type);
        if ($request->export_type == 'excel') {
            return Excel::download(new ProviderTaxExport($data), $store->name .'s TaxExport.xlsx');
        } else if ($request->export_type == 'csv') {
            return Excel::download(new providerTaxExport($data),  $store->name .'s TaxExport.csv');
        }
    }

        private function getprovidertaxData($store_id, $startDate, $endDate)
    {
        $summary = DB::table('trips')
            ->where('provider_id', $store_id)
            ->whereIn('trip_status', ['completed', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->selectRaw('COUNT(*) as total_orders, SUM(trip_amount) as total_order_amount, SUM(tax_amount) as total_tax')
            ->first();

        $orders = Trips::with([
            'orderTaxes' => function (MorphMany $query) {
                $query->where('order_type', Trips::class)
                    ->select('id', 'order_id', 'tax_name', 'tax_amount','tax_on','tax_type','taxable_type');
            }
        ])
            ->where('provider_id', $store_id)
            ->whereIn('trip_status', ['completed', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->select(['id', 'trip_amount', 'tax_amount','trip_type' ,'created_at'])
            ->latest('created_at');

        return ['summary' => $summary, 'orders' => $orders];
    }
}
