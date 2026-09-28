<?php


namespace Modules\Rental\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\MorphMany;
 use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;
use Modules\Rental\Entities\Trips;

class ProviderTaxReportController extends Controller
{

    public function providerTax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
            'from' => 'required',
            'to' => 'required',
        ]);

        $key = explode(' ', $request['search'] ?? '');
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $limit = $request['limite'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $from = $request->from;
        $to = $request->to;
        $store_id = $request->vendor->stores[0]->id;



        $startDate = Carbon::createFromFormat('m/d/Y', trim($from));
        $endDate = Carbon::createFromFormat('m/d/Y', trim($to));
        $startDate = $startDate->startOfDay();
        $endDate = $endDate->endOfDay();

        // $start = microtime(true);
        $vendortaxData =   $this->getVendortaxData($store_id, $startDate, $endDate, $key);
        $summary =   $vendortaxData['summary'];
        $orders = $vendortaxData['orders'];

        $totalOrders = $summary->total_orders;
        $totalOrderAmount = $summary->total_order_amount;
        $totalTax = $summary->total_tax;
        $taxSummary = $vendortaxData['taxSummary'];
        $orders = $orders->paginate($limit, ['*'], 'page', $offset);

        // $time = microtime(true) - $start;
        // dd("Query took {$time} seconds", $stores);
        $data = [
            'total_size' => $orders->total(),
            'limit' => $limit,
            'offset' => $offset,
            'taxSummary' => $taxSummary,
            'totalOrders' => (int) $totalOrders,
            'totalOrderAmount' => (float) $totalOrderAmount,
            'totalTax' =>(float) $totalTax,
            'orders' => $orders->items()
        ];
        return response()->json($data, 200);

    }


    private function getVendortaxData($store_id, $startDate, $endDate, $search)
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
                    ->select('id', 'order_id', 'tax_name', 'tax_amount', 'tax_type');
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


        $taxSummary = DB::table('order_taxes')
            ->select(
                'tax_name',
                DB::raw('SUM(tax_amount) as total_tax'),
                DB::raw("CONCAT(tax_rate) as tax_label")
            )
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
            ->groupBy('tax_name','tax_rate')
            ->get();


        return ['summary' => $summary, 'orders' => $orders, 'taxSummary' => $taxSummary ?? []];
    }
}
