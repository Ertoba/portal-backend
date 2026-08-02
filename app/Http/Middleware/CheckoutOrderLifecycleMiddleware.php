<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\CheckoutPaymentRecoveryService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckoutOrderLifecycleMiddleware
{
    public function __construct(
        private readonly CheckoutPaymentRecoveryService $recoveryService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('post') || !$request->is('api/v1/customer/order/place')) {
            return $next($request);
        }

        $reservation = $this->reserveIdempotencyKey($request);
        if ($reservation instanceof Response) {
            return $reservation;
        }

        $response = $next($request);

        if ($this->isEmptyCartResponse($response)) {
            $recovered = $this->recoveryService->recoverFromEmptyCart($request);
            if ($recovered instanceof JsonResponse) {
                $response = $recovered;
            }
        }

        $this->completeIdempotencyReservation($reservation, $response);

        return $response;
    }

    private function reserveIdempotencyKey(Request $request): array|Response|null
    {
        if (!Schema::hasTable('checkout_idempotencies')) {
            return null;
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9._~-]{16,100}$/', $key)) {
            return response()->json([
                'errors' => [[
                    'code' => 'invalid_idempotency_key',
                    'message' => 'The checkout idempotency key is invalid.',
                ]],
            ], 422);
        }

        [$userId, $isGuest] = $this->resolveOwner($request);
        if ($userId === null) {
            return null;
        }

        $fingerprint = $this->requestFingerprint($request, $userId, $isGuest);
        $moduleId = $this->moduleId($request);
        $now = now();

        $existing = DB::table('checkout_idempotencies')
            ->where('idempotency_key', $key)
            ->first();

        if ($existing) {
            return $this->handleExistingReservation(
                $existing,
                $key,
                $userId,
                $isGuest,
                $fingerprint
            );
        }

        try {
            $id = DB::table('checkout_idempotencies')->insertGetId([
                'idempotency_key' => $key,
                'user_id' => $userId,
                'is_guest' => $isGuest,
                'module_id' => $moduleId,
                'order_id' => null,
                'request_fingerprint' => $fingerprint,
                'status' => 'pending',
                'expires_at' => $now->copy()->addDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['id' => $id, 'key' => $key, 'owned' => true];
        } catch (QueryException) {
            $existing = DB::table('checkout_idempotencies')
                ->where('idempotency_key', $key)
                ->first();

            if (!$existing) {
                return null;
            }

            return $this->handleExistingReservation(
                $existing,
                $key,
                $userId,
                $isGuest,
                $fingerprint
            );
        }
    }

    private function handleExistingReservation(
        object $existing,
        string $key,
        string $userId,
        int $isGuest,
        string $fingerprint
    ): array|Response {
        if ((string) $existing->user_id !== $userId
            || (int) $existing->is_guest !== $isGuest
            || (string) $existing->request_fingerprint !== $fingerprint) {
            return response()->json([
                'errors' => [[
                    'code' => 'idempotency_key_reused',
                    'message' => 'This checkout key was already used for a different request.',
                ]],
            ], 409);
        }

        if ($existing->order_id) {
            $order = Order::withoutGlobalScopes()->find($existing->order_id);
            if ($order) {
                return $this->replayOrderResponse($order);
            }
        }

        $updatedAt = $existing->updated_at ? \Carbon\Carbon::parse($existing->updated_at) : null;
        if ($updatedAt && $updatedAt->greaterThan(now()->subMinutes(2))) {
            return response()->json([
                'errors' => [[
                    'code' => 'checkout_in_progress',
                    'message' => 'This checkout request is still being processed.',
                ]],
            ], 409);
        }

        DB::table('checkout_idempotencies')
            ->where('id', $existing->id)
            ->update([
                'status' => 'pending',
                'order_id' => null,
                'updated_at' => now(),
                'expires_at' => now()->addDay(),
            ]);

        return ['id' => $existing->id, 'key' => $key, 'owned' => true];
    }

    private function completeIdempotencyReservation(?array $reservation, Response $response): void
    {
        if (!$reservation || empty($reservation['owned']) || !Schema::hasTable('checkout_idempotencies')) {
            return;
        }

        $payload = $this->responsePayload($response);
        $orderId = data_get($payload, 'order_id');

        if ($response->getStatusCode() === 200 && $orderId) {
            DB::table('checkout_idempotencies')
                ->where('id', $reservation['id'])
                ->update([
                    'order_id' => (int) $orderId,
                    'status' => 'completed',
                    'updated_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);
            return;
        }

        DB::table('checkout_idempotencies')
            ->where('id', $reservation['id'])
            ->where('status', 'pending')
            ->delete();
    }

    private function isEmptyCartResponse(Response $response): bool
    {
        if ($response->getStatusCode() !== 403) {
            return false;
        }

        $payload = $this->responsePayload($response);
        return data_get($payload, 'errors.0.code') === 'empty_cart';
    }

    private function responsePayload(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function requestFingerprint(Request $request, string $userId, int $isGuest): string
    {
        $payload = $request->except(['order_attachment', 'password']);
        $this->sortRecursively($payload);

        return hash('sha256', json_encode([
            'user_id' => $userId,
            'is_guest' => $isGuest,
            'module_id' => $this->moduleId($request),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortRecursively(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursively($item);
            }
        }
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

        $guestId = $request->input('guest_id');
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

    private function replayOrderResponse(Order $order): JsonResponse
    {
        return response()->json([
            'message' => translate('messages.order_placed_successfully'),
            'order_id' => $order->id,
            'total_ammount' => (float) $order->order_amount,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'created_at' => $order->created_at,
            'user_id' => (int) $order->user_id,
            'idempotent_replay' => true,
        ]);
    }
}
