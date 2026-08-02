from pathlib import Path
import re

path = Path('app/Services/CheckoutPaymentRecoveryService.php')
text = path.read_text()

method = """    private function inspectOrderPayments(Order $order, bool $cancelActive): array
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

"""
text, count = re.subn(
    r"    private function inspectOrderPayments\(Order \$order, bool \$cancelActive\): array\n    \{.*?\n    \}\n\n(?=    private function logLifecycleResult)",
    method,
    text,
    count=1,
    flags=re.S,
)
if count != 1:
    raise SystemExit(f'inspectOrderPayments: expected 1 match, found {count}')

old = """        if (!$usesKeepz) {
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
"""
new = """        if (!$usesKeepz
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
"""
if text.count(old) != 1:
    raise SystemExit(f'superseded safety: expected 1 match, found {text.count(old)}')
text = text.replace(old, new, 1)

path.write_text(text)
