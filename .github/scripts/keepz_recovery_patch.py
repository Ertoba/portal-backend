from pathlib import Path
import re


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return text.replace(old, new, 1)


def sub_once(text: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return updated


lifecycle_path = Path("app/Services/KeepzGatewayLifecycleService.php")
lifecycle = lifecycle_path.read_text()

lifecycle = replace_once(
    lifecycle,
    "    private const LIVE_API_BASE_URL = 'https://gateway.keepz.me/ecommerce-service';\n    private const ACTIVE_STATUSES = ['initial', 'processing'];",
    "    private const LIVE_API_BASE_URL = 'https://gateway.keepz.me/ecommerce-service';\n    private const ORDER_NOT_FOUND_STATUS_CODE = 6005;\n    private const FINALIZED_STATUS_CODE = 6006;\n    private const STATUS_RETRY_DELAYS_MICROSECONDS = [300000, 800000];\n    private const ACTIVE_STATUSES = ['initial', 'processing'];",
    "lifecycle constants",
)

lifecycle = sub_once(
    lifecycle,
    r"        \$payload = \$this->sendEncryptedRequest\('GET', '/api/integrator/order/status', \[\n"
    r"            'integratorId' => trim\(\(string\) \$this->configValues->integrator_id\),\n"
    r"            'integratorOrderId' => \(string\) \$payment->id,\n"
    r"        \]\);\n\n"
    r"        if \(!is_array\(\$payload\) \|\| isset\(\$payload\['statusCode'\]\)\) \{\n"
    r"            \$this->logUnusableResponse\('Keepz recovery status was unavailable', \$payment, \$payload\);\n"
    r"            return \$this->result\('unknown', null, 'status_unavailable', \$payload\);\n"
    r"        \}",
    """        $payload = $this->statusPayload($payment);

        if (!is_array($payload)) {
            $this->logUnusableResponse('Keepz recovery status was unavailable', $payment, $payload);
            return $this->result('unknown', null, 'status_unavailable', $payload);
        }

        if ((int) ($payload['statusCode'] ?? 0) === self::ORDER_NOT_FOUND_STATUS_CODE) {
            $this->markAbsent($payment, $payload);
            return $this->result('absent', 'not_found', 'provider_order_absent', $payload);
        }

        if (isset($payload['statusCode'])) {
            $this->logUnusableResponse('Keepz recovery status was unavailable', $payment, $payload);
            return $this->result('unknown', null, 'status_unavailable', $payload);
        }""",
    "inspect status handling",
)

lifecycle = replace_once(
    lifecycle,
    "        return $this->result('unknown', $status, 'unrecognized_status', $payload);",
    "        $this->logUnusableResponse('Keepz recovery returned an unrecognized status', $payment, $payload);\n        return $this->result('unknown', $status, 'unrecognized_status', $payload);",
    "unrecognized status logging",
)

lifecycle = sub_once(
    lifecycle,
    r"        // Keepz returns 6006 when an order is already finalised\. Resolve the\n"
    r"        // authoritative state before allowing a retry or a COD conversion\.\n"
    r"        if \(is_array\(\$payload\) && \(int\) \(\$payload\['statusCode'\] \?\? 0\) === 6006\) \{\n"
    r"            return \$this->inspect\(\$payment\);\n"
    r"        \}",
    """        if (is_array($payload)
            && (int) ($payload['statusCode'] ?? 0) === self::ORDER_NOT_FOUND_STATUS_CODE) {
            $this->markAbsent($payment, $payload);
            return $this->result('absent', 'not_found', 'provider_order_absent', $payload);
        }

        // Keepz returns 6006 when an order is already finalised. Resolve the
        // authoritative state before allowing a retry or a COD conversion.
        if (is_array($payload)
            && (int) ($payload['statusCode'] ?? 0) === self::FINALIZED_STATUS_CODE) {
            return $this->inspect($payment);
        }""",
    "cancel status handling",
)

lifecycle = replace_once(
    lifecycle,
    "    private function finalizeSuccessfulPayment(PaymentRequest $payment, array $payload): void\n    {",
    """    private function statusPayload(PaymentRequest $payment): ?array
    {
        $payload = null;
        $delays = array_merge([0], self::STATUS_RETRY_DELAYS_MICROSECONDS);

        foreach ($delays as $attempt => $delay) {
            if ($delay > 0) {
                usleep($delay);
            }

            $payload = $this->sendEncryptedRequest('GET', '/api/integrator/order/status', [
                'integratorId' => trim((string) $this->configValues->integrator_id),
                'integratorOrderId' => (string) $payment->id,
            ]);

            if (!is_array($payload)
                || (int) ($payload['statusCode'] ?? 0) !== self::ORDER_NOT_FOUND_STATUS_CODE) {
                return $payload;
            }

            if ($attempt < count($delays) - 1) {
                Log::info('Keepz recovery status was not found; retrying', [
                    'payment_request_id' => $payment->id,
                    'order_id' => $payment->attribute_id,
                    'attempt' => $attempt + 1,
                    'next_delay_ms' => (int) ($delays[$attempt + 1] / 1000),
                    'status_code' => data_get($payload, 'statusCode'),
                    'exception_group' => data_get($payload, 'exceptionGroup'),
                    'message' => data_get($payload, 'message'),
                ]);
            }
        }

        return $payload;
    }

    private function markAbsent(PaymentRequest $payment, array $payload): void
    {
        $metadata = $this->metadata($payment);
        $handledAt = now()->toIso8601String();
        $metadata['keepz_recovery_absent_at'] = $handledAt;
        $metadata['keepz_recovery_absent_payload'] = $this->safePayload($payload);
        $metadata['keepz_failure_handled'] = $metadata['keepz_failure_handled'] ?? $handledAt;
        $metadata['keepz_failure_status'] = 'provider_order_absent';
        $this->storeMetadata($payment, $metadata);
    }

    private function finalizeSuccessfulPayment(PaymentRequest $payment, array $payload): void
    {""",
    "lifecycle helpers",
)

lifecycle = replace_once(
    lifecycle,
    """        $decoded = json_decode($response ?: '', true);
        if (!is_array($decoded)) {
            Log::warning('Keepz recovery returned non JSON', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'body_preview' => Str::limit((string) $response, 300, ''),
            ]);
            return null;
        }

        return $this->decodeKeepzResponse($decoded);
""",
    """        $decoded = json_decode($response ?: '', true);
        if (!is_array($decoded)) {
            Log::warning('Keepz recovery returned non JSON', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'body_preview' => Str::limit((string) $response, 300, ''),
            ]);
            return null;
        }

        if ($httpCode >= 400 || isset($decoded['statusCode'])) {
            Log::warning('Keepz recovery returned an HTTP error', [
                'endpoint' => $endpoint,
                'integrator_order_id' => $payload['integratorOrderId'] ?? null,
                'http_code' => $httpCode,
                'status_code' => data_get($decoded, 'statusCode'),
                'exception_group' => data_get($decoded, 'exceptionGroup'),
                'message' => data_get($decoded, 'message'),
            ]);
        }

        return $this->decodeKeepzResponse($decoded);
""",
    "HTTP diagnostics",
)

lifecycle = replace_once(
    lifecycle,
    """        Log::warning($message, [
            'payment_request_id' => $payment->id,
            'status' => data_get($payload, 'status'),
            'status_code' => data_get($payload, 'statusCode'),
            'message' => data_get($payload, 'message'),
        ]);
""",
    """        Log::warning($message, [
            'payment_request_id' => $payment->id,
            'order_id' => $payment->attribute_id,
            'status' => data_get($payload, 'status'),
            'status_code' => data_get($payload, 'statusCode'),
            'exception_group' => data_get($payload, 'exceptionGroup'),
            'message' => data_get($payload, 'message'),
        ]);
""",
    "unusable response diagnostics",
)

lifecycle_path.write_text(lifecycle)

recovery_path = Path("app/Services/CheckoutPaymentRecoveryService.php")
recovery = recovery_path.read_text()

status_method = """    public function status(Request $request, int $orderId): JsonResponse
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

        if (!$usesKeepz) {
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

"""
recovery = sub_once(
    recovery,
    r"    public function status\(Request \$request, int \$orderId\): JsonResponse\n    \{.*?\n    \}\n\n(?=    public function prepareRetry)",
    status_method,
    "status method",
)

count = recovery.count("            ->where('payment_method', 'digital_payment')")
if count != 2:
    raise SystemExit(f"payment method query: expected 2 matches, found {count}")
recovery = recovery.replace(
    "            ->where('payment_method', 'digital_payment')",
    "            ->whereIn('payment_method', ['digital_payment', 'keepz'])",
)

count = recovery.count("if ($this->latestKeepzPayment($order))")
if count != 2:
    raise SystemExit(f"payment existence check: expected 2 matches, found {count}")
recovery = recovery.replace(
    "if ($this->latestKeepzPayment($order))",
    "if ($this->hasKeepzPayment($order))",
)

recovery = replace_once(
    recovery,
    """        if ($order->payment_method !== 'digital_payment') {
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
""",
    """        if (!in_array($order->payment_method, ['digital_payment', 'keepz'], true)) {
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
""",
    "COD method eligibility",
)

recovery = replace_once(
    recovery,
    "                if ($lockedOrder->payment_method === 'digital_payment') {",
    "                if (in_array($lockedOrder->payment_method, ['digital_payment', 'keepz'], true)) {",
    "COD transaction method eligibility",
)

retry_methods = """    private function prepareOrderForPaymentRetry(Order $order): array
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
                    return $inspection;
                }

                $cancellation = $this->keepzGateway->cancel($payment);
                $this->logLifecycleResult($order, $payment, 'cancel', $cancellation);

                if ($cancellation['state'] === 'paid') {
                    return $cancellation;
                }

                if (in_array($cancellation['state'], ['terminal', 'absent'], true)) {
                    continue;
                }

                return [
                    'state' => 'blocked',
                    'provider_status' => $cancellation['provider_status'] ?? null,
                    'error' => $cancellation['error'] ?? 'cancel_failed',
                ];
            }

            return [
                'state' => 'unknown',
                'provider_status' => $inspection['provider_status'] ?? null,
                'error' => $inspection['error'] ?? 'status_unavailable',
            ];
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

"""
recovery = sub_once(
    recovery,
    r"    private function prepareOrderForPaymentRetry\(Order \$order\): array\n    \{.*?\n    \}\n\n(?=    private function findMatchingRecoverableOrder)",
    retry_methods,
    "retry lifecycle methods",
)

payment_helpers = """    private function keepzPayments(Order $order)
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

"""
recovery = sub_once(
    recovery,
    r"    private function latestKeepzPayment\(Order \$order\): \?PaymentRequest\n    \{.*?\n    \}\n\n(?=    private function resolveOwner)",
    payment_helpers,
    "payment helpers",
)

recovery_path.write_text(recovery)
