<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Models\BusinessSetting;
use App\Models\DisbursementDetails;
use App\Models\Expense;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;
use App\Models\Order;
use App\Models\PaymentRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function expense_report(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
            'from' => 'required',
            'to' => 'required',
        ]);

        $key = explode(' ', $request['search']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $limit = $request['limite'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $from = $request->from;
        $to = $request->to;
        $store_id = $request->vendor->stores[0]->id;

        $expense = Expense::where('created_by', 'vendor')->where('store_id', $store_id)->where('amount', '>', 0)
            ->when(isset($from) &&  isset($to), function ($query) use ($from, $to) {
                $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:29']);
            })->when(isset($key), function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('order_id', 'like', "%{$value}%");
                    }
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);
        $data = [
            'total_size' => $expense->total(),
            'limit' => $limit,
            'offset' => $offset,
            'expense' => $expense->items()
        ];
        return response()->json($data, 200);
    }

    public function disbursement_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;

        $store_id = $request?->vendor?->stores[0]?->id;
        $store_ids = $request?->vendor?->stores?->pluck('id')->filter()->values() ?? collect();

        $total_disbursements = DisbursementDetails::where('store_id', $store_id)->orderBy('created_at', 'desc')->get();
        $paginator = DisbursementDetails::where('store_id', $store_id)->latest()->paginate($limit, ['*'], 'page', $offset);

        $paginator->each(function ($data) {
            $data->withdraw_method?->method_fields ?  $data->withdraw_method->method_fields = json_decode($data->withdraw_method?->method_fields, true) : '';
        });

        $keepz_settlement_query = PaymentRequest::query()
            ->join('orders', 'orders.id', '=', 'payment_requests.attribute_id')
            ->whereIn('orders.store_id', $store_ids)
            ->whereIn('payment_requests.attribute', ['order', 'order_place'])
            ->where('payment_requests.payment_method', 'keepz')
            ->where('payment_requests.is_paid', 1)
            ->whereNotNull('payment_requests.additional_data')
            ->whereRaw(
                "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(payment_requests.additional_data, '$.keepz_split_vendor_amount')) AS DECIMAL(24,8)), 0) > 0"
            );

        $keepz_completed = (float) (clone $keepz_settlement_query)
            ->selectRaw(
                "COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payment_requests.additional_data, '$.keepz_split_vendor_amount')) AS DECIMAL(24,8))), 0) as total"
            )
            ->value('total');

        $keepz_paginator = (clone $keepz_settlement_query)
            ->select([
                'payment_requests.id as payment_request_id',
                'payment_requests.transaction_id',
                'payment_requests.additional_data',
                'payment_requests.created_at',
                'payment_requests.updated_at',
                'orders.id as order_id',
                'orders.store_id',
            ])
            ->latest('payment_requests.updated_at')
            ->paginate($limit, ['*'], 'keepz_page', $offset);

        $keepz_settlements = collect($keepz_paginator->items())->map(function ($payment) {
            $metadata = json_decode($payment->additional_data ?: '[]', true);
            $receiver_identifier = (string) ($metadata['keepz_split_vendor_receiver_identifier'] ?? '');
            $paid_at = $metadata['keepz_paid_at'] ?? $payment->updated_at ?? $payment->created_at;

            return [
                'payment_request_id' => $payment->payment_request_id,
                'order_id' => (int) $payment->order_id,
                'store_id' => (int) $payment->store_id,
                'amount' => round((float) ($metadata['keepz_split_vendor_amount'] ?? 0), 2),
                'status' => 'completed',
                'transaction_id' => $payment->transaction_id,
                'receiver_type' => $metadata['keepz_split_vendor_receiver_type'] ?? null,
                'receiver_identifier_masked' => $this->maskKeepzReceiver($receiver_identifier),
                'paid_at' => $paid_at ? Carbon::parse($paid_at)->format('Y-m-d H:i:s') : null,
            ];
        })->values();

        $data = [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'pending' => (float) $total_disbursements->where('status', 'pending')->sum('disbursement_amount'),
            'completed' => (float) $total_disbursements->where('status', 'completed')->sum('disbursement_amount'),
            'canceled' => (float) $total_disbursements->where('status', 'canceled')->sum('disbursement_amount'),
            'complete_day' => (int) BusinessSetting::where(['key' => 'store_disbursement_waiting_time'])->first()?->value,
            'disbursements' => $paginator->items(),
            'keepz_total_size' => $keepz_paginator->total(),
            'keepz_completed' => $keepz_completed,
            'keepz_settlements' => $keepz_settlements,
        ];
        return response()->json($data, 200);
    }

    private function maskKeepzReceiver(string $receiver): ?string
    {
        $receiver = trim($receiver);
        if ($receiver === '') {
            return null;
        }

        $visible_start = str_starts_with(strtoupper($receiver), 'GE') ? 4 : 8;
        if (strlen($receiver) <= $visible_start + 4) {
            return str_repeat('*', max(4, strlen($receiver)));
        }

        return substr($receiver, 0, $visible_start)
            . str_repeat('*', strlen($receiver) - $visible_start - 4)
            . substr($receiver, -4);
    }



    public function vendorTax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
            'from' => 'required',
            'to' => 'required',
        ]);

        $key = explode(' ', $request['search']);
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
            'totalTax' => (float)  $totalTax,
            'orders' => $orders->items()
        ];
        return response()->json($data, 200);

    }


    private function getVendortaxData($store_id, $startDate, $endDate, $search)
    {
        $summary = DB::table('orders')
            ->where('store_id', $store_id)
            ->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->when(count($search), fn($q) => $q->where(function ($q) use ($search) {
                foreach ($search as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            }))
            ->selectRaw('COUNT(*) as total_orders, SUM(order_amount) as total_order_amount, SUM(total_tax_amount) as total_tax')
            ->first();

        $orders = Order::with([
            'orderTaxes' => function (MorphMany $query) {
                $query->where('order_type', Order::class)
                    ->select('id', 'order_id', 'tax_name', 'tax_amount', 'tax_type');
            }
        ])

            ->where('store_id', $store_id)
            ->when(count($search), fn($q) => $q->where(function ($q) use ($search) {
                foreach ($search as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            }))
            ->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled'])
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->select(['id', 'order_amount', 'total_tax_amount', 'order_type', 'created_at', 'order_status', 'payment_status'])
            ->latest('created_at');


        $taxSummary = DB::table('order_taxes')
          ->select(
                'tax_name',
                DB::raw('SUM(tax_amount) as total_tax'),
                DB::raw("CONCAT(tax_rate) as tax_label")
            )
            ->where('order_type', Order::class)
            ->when(count($search), fn($q) => $q->where(function ($q) use ($search) {
                foreach ($search as $value) {
                    $q->orWhere('order_id', 'like', "%{$value}%");
                }
            }))
            ->whereIn('order_id', $orders->pluck('id')->toArray())
            ->where('store_id', $store_id)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('tax_name', 'tax_rate')
            ->get();


        return ['summary' => $summary, 'orders' => $orders, 'taxSummary' => $taxSummary ?? []];
    }
}
