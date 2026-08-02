<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutPaymentRecoveryService
{
    public function __construct(
        private readonly KeepzGatewayLifecycleService $keepzGateway
    ) {
    }

    public function recoverFromEmptyCart(Request $request): ?JsonResponse
    {
        $requestedMethod = (string) $request->input('payment_method');
        if (!in_array($requestedMethod, ['digital_payment', 'cash_on_delivery'], true)) {
            return null;
        }

        $order = $this->findMatchingRecoverableOrder($request);
        if (!$order) {
            return null;
        }

        if ($requestedMethod === 'cash_on_delivery') {
            return $this->switchOrderToCod($request, $order);
        }

        $resolution = $this->prepareOrderForPaymentRetry($order);
        if ($resolution['state'] === 'paid') {
            return $this->errorResponse(
                'order_already_paid',
                'This order has already been paid.',
                409,
                $order
            );
        }

        if ($resolution['state'] !== 'ready') {
            return $this->errorResponse(
                'payment_status_unavailable',
                'The previous payment status could not be verified. Please try again shortly.',
                409,
                $order
            );
        }

        return $this->orderResponse($order, [
            'recovered_order' => true,
            'payment_retry_ready' => true,
        ]);
    }

    public function status(Request $request, int $orderId): JsonResponse
    {
        $order = $this->findOwnedOrder($request, $orderId);
        if (!$order) {
            return $this->errorResponse('order_not_found', 'Order not found.', 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'order_id' => $order->id,
                'state' => 'paid',
                'provider_status' => 'success',
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'order_status' => $order->order_status,
            ]);
        }

        $usesKeepz = in_array($order->payment_method, ['digital_payment', 'keepz'], true);
        $inspection = $this->inspectOrderPayments($order, false);
        $order->refresh();

        if ($inspection['state'] === 'paid' || $order->payment_status === 'paid') {
            return response()->json([
                'order_id' => $order->id,
                'state' => 'paid',
                'provider_status' => 'success',
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'order_status' => $order->order_status,
            ]);
        }

        if (!$usesKeepz
            && !in_array($inspection['state'], ['active', 'unknown', 'blocked'], true)) {
            return response()->json([
                'order_id' => $order->id,
                'state' => 'superseded',
                'provider_status' => $inspection['provider_status'] ?? null,
                'provider_error' => $inspection['error'] ?? null,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'order_status' => $order->order_status,
            ]);
        }

        $state = $inspection['state'];
        return response()->json([
            'order_id' => $order->id,
            'state' => $state,
            'provider_status' => $inspection['provider_status'] ?? null,
            'provider_error' => $inspection['error'] ?? null,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'order_status' => $order->order_status,
        ], in_array($state, ['unknown', 'blocked'], true) ? 409 : 200);
    }

    public function prepareRetry(Request $request, int $orderId): JsonResponse
    {
        $order = $this->findOwnedOrder($request, $orderId);
        if (!$order) {
            return $this->errorResponse('order_not_found', 'Order not found.', 404);
        }

        $resolution = $this->prepareOrderForPaymentRetry($order);
        if ($resolution['state'] === 'paid') {
            return $this->errorResponse(
                'order_already_paid',
                'This order has already been paid.',
                409,
                $order
            );
        }

        if ($resolution['state'] !== 'ready') {
            return $this->errorResponse(
                'payment_status_unavailable',
                'The payment status could not be verified safely.',
                409,
                $order
            );
        }

        return response()->json([
            'order_id' => $order->id,
            'state' => 'ready',
            'payment_retry_ready' => true,
        ]);
    }

    public function switchToCod(Request $request, int $orderId): JsonResponse
    {
        $order = $this->findOwnedOrder($request, $orderId);
        if (!$order) {
            return $this->errorResponse('order_not_found', 'Order not found.', 404);
        }

        return $this->switchOrderToCod($request, $order);
    }

    public function recoverable(Request $request): JsonResponse
    {
        [$userId, $isGuest] = $this->resolveOwner($request);
        if ($userId === null) {
            return response()->json(['recoverable' => false]);
        }

        $query = Order::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->whereIn('payment_method', ['digital_payment', 'keepz'])
            ->where('payment_status', 'unpaid')
            ->whereIn('order_status', ['failed', 'pending'])
            ->where('created_at', '>=', now()->subDay())
            ->latest();

        $moduleId = $this->moduleId($request);
        if ($moduleId !== null) {
            $query->where('module_id', $moduleId);
        }

        foreach ($query->limit(5)->get() as $order) {
            if ($this->hasKeepzPayment($order)) {
                return response()->json([
                    'recoverable' => true,
                    'order_id' => $order->id,
                    'order_amount' => (float) $order->order_amount,
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'store_id' => $order->store_id,
                    'module_id' => $order->module_id,
                    'created_at' => optional($order->created_at)->toIso8601String(),
                ]);
            }
        }

        return response()->json(['recoverable' => false]);
    }

    private function switchOrderToCod(Request $request, Order $order): JsonResponse
    {
        if ($order->payment_status === 'paid') {
            return $this->errorResponse(
                'order_already_paid',
                'This order has already been paid.',
                409,
                $order
            );
        }

        if (!in_array($order->payment_method, ['digital_payment', 'keepz'], true)) {
            if ($order->payment_method === 'cash_on_delivery') {
                return $this->orderResponse($order, ['switched_to_cod' => true]);
            }
            return $this->errorResponse(
                'payment_method_not_switchable',
                'This payment method cannot be changed to cash on delivery.',
                409,
                $order
            );
        }

        $resolution = $this->prepareOrderForPaymentRetry($order);
        if ($resolution['state'] === 'paid') {
            return $this->errorResponse(
                'order_already_paid',
                'This order has already been paid.',
                409,
                $order
            );
        }

        if ($resolution['state'] !== 'ready') {
            return $this->errorResponse(
                'payment_status_unavailable',
                'The previous card payment could not be canceled safely.',
                409,
                $order
            );
        }

        try {
            $order = DB::transaction(function () use ($order) {
                $lockedOrder = Order::withoutGlobalScopes()->lockForUpdate()->find($order->id);
                if (!$lockedOrder) {
                    return null;
                }

                if ($lockedOrder->payment_status === 'paid') {
                    return $lockedOrder;
                }

                if (in_array($lockedOrder->payment_method, ['digital_payment', 'keepz'], true)) {
                    $lockedOrder->payment_method = 'cash_on_delivery';
                    $lockedOrder->payment_status = 'unpaid';
                    $lockedOrder->order_status = 'pending';
                    $lockedOrder->failed = null;
                    $lockedOrder->pending = $lockedOrder->pending ?: now();
                    $lockedOrder->save();

                    OrderPayment::where('order_id', $lockedOrder->id)
                        ->where('payment_status', 'unpaid')
                        ->update(['payment_method' => 'cash_on_delivery']);
                }

                return $lockedOrder;
            });
        } catch (Throwable $exception) {
            Log::error('Failed to switch recovered order to COD', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
            return $this->errorResponse(
                'cod_switch_failed',
                'The payment method could not be changed.',
                500,
                $order
            );
        }

        if (!$order) {
            return $this->errorResponse('order_not_found', 'Order not found.', 404);
        }

        if ($order->payment_status === 'paid') {
            return $this->errorResponse(
                'order_already_paid',
                'This order has already been paid.',
                409,
                $order
            );
        }

        try {
            Helpers::send_order_notification($order);
        } catch (Throwable $exception) {
            Log::warning('Recovered COD order notification failed', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->orderResponse($order, [
            'recovered_order' => true,
            'switched_to_cod' => true,
        ]);
    }

    private function prepareOrderForPaymentRetry(Order $order): array
    {
        $inspection = $this->inspectOrderPayments($order, true);

        if ($inspection['state'] === 'paid') {
            return ['state' => 'paid'];
        }

        if ($inspection['state'] === 'ready') {
            return ['state' => 'ready'];
        }

        return ['state' => 'blocked'];
    }

    private function inspectOrderPayments(Order $order, bool $cancelActive): array
    {
        if ($order->payment_status === 'paid') {
            return ['state' => 'paid', 'provider_status' => 'success', 'error' => null];
        }

        $payments = $this->keepzPayments($order);
        if ($payments->isEmpty()) {
            return ['state' => 'ready', 'provider_status' => null, 'error' => null];
        }

        $lastProviderStatus = null;
        $lastError = null;
        $activeResult = null;
        $blockingResult = null;

        foreach ($payments as $payment) {
            $inspection = $this->keepzGateway->inspect($payment);
            $this->logLifecycleResult($order, $payment, 'inspect', $inspection);
            $lastProviderStatus = $inspection['provider_status'] ?? $lastProviderStatus;
            $lastError = $inspection['error'] ?? $lastError;

            if ($inspection['state'] === 'paid') {
                return $inspection;
            }

            if (in_array($inspection['state'], ['terminal', 'absent'], true)) {
                continue;
            }

            if ($inspection['state'] === 'active') {
                if (!$cancelActive) {
                    $activeResult ??= $inspection;
                    continue;
                }

                $cancellation = $this->keepzGateway->cancel($payment);
                $this->logLifecycleResult($order, $payment, 'cancel', $cancellation);

                if ($cancellation['state'] === 'paid') {
                    return $cancellation;
                }

                if (in_array($cancellation['state'], ['terminal', 'absent'], true)) {
                    continue;
                }

                $blockingResult ??= [
                    'state' => 'blocked',
                    'provider_status' => $cancellation['provider_status'] ?? null,
                    'error' => $cancellation['error'] ?? 'cancel_failed',
                ];
                continue;
            }

            $blockingResult ??= [
                'state' => $cancelActive ? 'blocked' : 'unknown',
                'provider_status' => $inspection['provider_status'] ?? null,
                'error' => $inspection['error'] ?? 'status_unavailable',
            ];
        }

        if ($blockingResult !== null) {
            return $blockingResult;
        }

        if ($activeResult !== null) {
            return $activeResult;
        }

        return [
            'state' => 'ready',
            'provider_status' => $lastProviderStatus,
            'error' => $lastError,
        ];
    }

    private function logLifecycleResult(
        Order $order,
        PaymentRequest $payment,
        string $action,
        array $result
    ): void {
        Log::info('Keepz checkout recovery decision', [
            'order_id' => $order->id,
            'payment_request_id' => $payment->id,
            'action' => $action,
            'state' => $result['state'] ?? null,
            'provider_status' => $result['provider_status'] ?? null,
            'error' => $result['error'] ?? null,
            'status_code' => data_get($result, 'payload.statusCode'),
            'exception_group' => data_get($result, 'payload.exceptionGroup'),
        ]);
    }

    private function findMatchingRecoverableOrder(Request $request): ?Order
    {
        [$userId, $isGuest] = $this->resolveOwner($request);
        if ($userId === null) {
            return null;
        }

        $query = Order::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->whereIn('payment_method', ['digital_payment', 'keepz'])
            ->where('payment_status', 'unpaid')
            ->whereIn('order_status', ['failed', 'pending'])
            ->where('created_at', '>=', now()->subHours(2))
            ->latest();

        $moduleId = $this->moduleId($request);
        if ($moduleId !== null) {
            $query->where('module_id', $moduleId);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', (string) $request->input('order_type'));
        }

        if ($request->filled('order_amount')) {
            $amount = (float) $request->input('order_amount');
            $query->whereBetween('order_amount', [$amount - 0.02, $amount + 0.02]);
        }

        foreach ($query->limit(5)->get() as $order) {
            if ($order->order_type === 'parcel') {
                continue;
            }
            if ($this->hasKeepzPayment($order)) {
                return $order;
            }
        }

        return null;
    }

    private function findOwnedOrder(Request $request, int $orderId): ?Order
    {
        [$userId, $isGuest] = $this->resolveOwner($request);
        if ($userId === null) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->first();
    }

    private function keepzPayments(Order $order)
    {
        return PaymentRequest::whereIn('attribute', ['order', 'order_place'])
            ->where('attribute_id', $order->id)
            ->where('payment_method', 'keepz')
            ->latest()
            ->get();
    }

    private function hasKeepzPayment(Order $order): bool
    {
        return PaymentRequest::whereIn('attribute', ['order', 'order_place'])
            ->where('attribute_id', $order->id)
            ->where('payment_method', 'keepz')
            ->exists();
    }

    private function resolveOwner(Request $request): array
    {
        $user = $request->input('user');
        if (!$user && $request->header('Authorization') && $request->header('Authorization') !== 'Bearer null') {
            $user = auth('api')->user();
        }

        if ($user) {
            return [(string) $user->id, 0];
        }

        $guestId = $request->input('guest_id', $request->query('guest_id'));
        if ($guestId === null || $guestId === '') {
            return [null, 1];
        }

        return [(string) $guestId, 1];
    }

    private function moduleId(Request $request): ?int
    {
        $header = $request->header('moduleId');
        if ($header === null || $header === '') {
            return null;
        }

        $moduleId = getModuleId($header);
        return $moduleId ? (int) $moduleId : null;
    }

    private function orderResponse(Order $order, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'message' => translate('messages.order_placed_successfully'),
            'order_id' => $order->id,
            'total_ammount' => (float) $order->order_amount,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'created_at' => $order->created_at,
            'user_id' => (int) $order->user_id,
        ], $extra));
    }

    private function errorResponse(
        string $code,
        string $message,
        int $status,
        ?Order $order = null
    ): JsonResponse {
        $payload = [
            'errors' => [[
                'code' => $code,
                'message' => $message,
            ]],
        ];

        if ($order) {
            $payload['order_id'] = $order->id;
            $payload['payment_status'] = $order->payment_status;
            $payload['order_status'] = $order->order_status;
        }

        return response()->json($payload, $status);
    }
}
