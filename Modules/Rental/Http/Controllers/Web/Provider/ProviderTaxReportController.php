<?php


namespace Modules\Rental\Http\Controllers\Web\Provider;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Maatwebsite\Excel\Facades\Excel;
use App\CentralLogics\Helpers;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Exports\ProviderTaxExport;

class ProviderTaxReportController extends Controller
{

    public function providerTax(Request $request)
    {

        $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        $key = explode(' ', $request['search'] ?? '');
        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();


        $store = Helpers::get_store_data();

        // $start = microtime(true);
        $vendortaxData =   $this->getVendortaxData($store->id, $startDate, $endDate, $key);
        $summary =   $vendortaxData['summary'];
        $orders = $vendortaxData['orders'];

        $totalOrders = $summary->total_orders;
        $totalOrderAmount = $summary->total_order_amount;
        $totalTax = $summary->total_tax;
        $taxSummary = $vendortaxData['taxSummary'];
        $orders = $orders->paginate(config('default_pagination'))
            ->withQueryString();

        // $time = microtime(true) - $start;
        // dd("Query took {$time} seconds", $stores);


        return view('rental::provider.report.tax-report.vendor-tax-detail-report', compact('totalOrders', 'totalOrderAmount', 'totalTax', 'store', 'orders', 'startDate', 'endDate', 'taxSummary','dateRange'));
    }

    public function providerTaxExport(Request $request)
    {
        $dateRange = $request->dates ?? now()->subDays(6)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        $key = explode(' ', $request['search'] ?? '');
        list($startDate, $endDate) = explode(' - ', $dateRange);
        $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();


        $store = Helpers::get_store_data();

        // $start = microtime(true);
        $vendortaxData =   $this->getVendortaxData($store->id, $startDate, $endDate, $key, true);
        $summary =   $vendortaxData['summary'];
        $orders = $vendortaxData['orders'];
        // $taxSummary = $vendortaxData['taxSummary'];

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
            return Excel::download(new ProviderTaxExport($data), $store->name . 's TaxExport.xlsx');
        } else if ($request->export_type == 'csv') {
            return Excel::download(new ProviderTaxExport($data),  $store->name . 's TaxExport.csv');
        }
    }

    private function getVendortaxData($store_id, $startDate, $endDate, $search, $export = false)
    {
        $summary = DB::table('trips')
            ->where('provider_id', $store_id)
            ->whereIn('trip_status', ['completed', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->when(count($search), fn($q) => $q->where(function ($q) use ($search) {
                foreach ($search as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            }))
            ->selectRaw('COUNT(*) as total_orders, SUM(trip_amount) as total_order_amount, SUM(tax_amount) as total_tax')
            ->first();

        $orders = Trips::with([
            'orderTaxes' => function (MorphMany $query) {
                $query->where('order_type', Trips::class)
                    ->select('id', 'order_id', 'tax_name', 'tax_amount','tax_on','tax_type','taxable_type');
            }
        ])

            ->where('provider_id', $store_id)
            ->when(count($search), fn($q) => $q->where(function ($q) use ($search) {
                foreach ($search as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            }))
            ->whereIn('trip_status', ['completed', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->select(['id', 'trip_amount', 'tax_amount', 'trip_type', 'created_at', 'trip_status', 'payment_status'])
            ->latest('created_at');

        if(!$export){
            $taxSummary = DB::table('order_taxes')
                ->select('tax_name', DB::raw('SUM(tax_amount) as total_tax'))
                ->where('order_type', Trips::class)
                ->when(count($search), fn($q) => $q->where(function ($q) use ($search) {
                    foreach ($search as $value) {
                        $q->orWhere('order_id', 'like', "%{$value}%");
                    }
                }))
                ->where('store_id', $store_id)
                ->whereIn('order_id', $orders->pluck('id')->toArray())
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->groupBy('tax_name')
                ->get();

        }
        return ['summary' => $summary, 'orders' => $orders, 'taxSummary' => $taxSummary??[]];
    }
}
