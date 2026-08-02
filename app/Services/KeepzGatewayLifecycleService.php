<?php

namespace App\Services;

use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

class KeepzGatewayLifecycleService
{
    private const TEST_API_BASE_URL = 'https://gateway.dev.keepz.me/ecommerce-service';
    private const LIVE_API_BASE_URL = 'https://gateway.keepz.me/ecommerce-service';
    private const ACTIVE_STATUSES = ['initial', 'processing'];
    private const TERMINAL_STATUSES = [
        'failed',
        'failure',
        'canceled',
        'cancelled',
        'expired',
        'refund_requested',
        'partially_refunded',
        'refunded_by_operator',
        'refunded_by_integrator',
        'refunded_by_keepz',
        'refunded_failed',
    ];

    private mixed $configValues = null;
    private bool $isLiveMode = false;

    public function __construct()
    {
        try {
            $config = DB::table('addon_settings')
                ->where('key_name', 'keepz')
                ->where('settings_type', 'payment_config')
                ->first();

            if ($config) {
                $this->isLiveMode = $config->mode === 'live';
                $values = $this->isLiveMode ? $config->live_values : $config->test_values;
                $this->configValues = json_decode($values ?: 'null');
            }
        } catch (Throwable $exception) {
            Log::warning('Keepz recovery configuration could not be loaded', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function inspect(PaymentRequest $payment): array
    {
        if ((int) $payment->is_paid === 1) {
            return $this->result('paid', 'success');
        }

        if (!$this->isConfigured()) {
            return $this->result('unknown', null, 'keepz_not_configured');
        }

        $payload = $this->sendEncryptedRequest('GET', '/api/integrator/order/status', [
            'integratorId' => trim((string) $this->configValues->integrator_id),
            'integratorOrderId' => (string) $payment->id,
        ]);

        if (!is_array($payload) || isset($payload['statusCode'])) {
            $this->logUnusableResponse('Keepz recovery status was unavailable', $payment, $payload);
            return $this->result('unknown', null, 'status_unavailable', $payload);
        }

        $status = $this->normalizeStatus(data_get($payload, 'status') ?? data_get($payload, 'orderStatus'));

        if ($status === 'success') {
            if (!$this->matchesPaymentRequest($payment, $payload)) {
                return $this->result('unknown', $status, 'amount_or_currency_mismatch', $payload);
            }

            $this->finalizeSuccessfulPayment($payment, $payload);
            return $this->result('paid', $status, null, $payload);
        }

        if (in_array($status, self::ACTIVE_STATUSES, true)) {
            return $this->result('active', $status, null, $payload);
        }

        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            $this->markTerminal($payment, $status, $payload);
            return $this->result('terminal', $status, null, $payload);
        }

        return $this->result('unknown', $status, 'unrecognized_status', $payload);
    }

    public function cancel(PaymentRequest $payment): array
    {
        if (!$this->isConfigured()) {
            return $this->result('unknown', null, 'keepz_not_configured');
        }

        $payload = $this->sendEncryptedRequest('DELETE', '/api/integrator/order/cancel', [
            'integratorId' => trim((string) $this->configValues->integrator_id),
            'integratorOrderId' => (string) $payment->id,
        ]);

        $status = is_array($payload)
            ? $this->normalizeStatus(data_get($payload, 'status') ?? data_get($payload, 'orderStatus'))
            : null;

        if (in_array($status, ['canceled', 'cancelled'], true)) {
            $this->markTerminal($payment, 'canceled', $payload);
            return $this->result('terminal', 'canceled', null, $payload);
        }

        // Keepz returns 6006 when an order is already finalised. Resolve the
        // authoritative state before allowing a retry or a COD conversion.
        if (is_array($payload) && (int) ($payload['statusCode'] ?? 0) === 6006) {
            return $this->inspect($payment);
        }

        $this->logUnusableResponse('Keepz recovery cancellation failed', $payment, $payload);
        return $this->result('unknown', $status, 'cancel_failed', $payload);
    }

    private function finalizeSuccessfulPayment(PaymentRequest $payment, array $payload): void
    {
        $transactionId = (string) (
            data_get($payload, 'orderId')
            ?? data_get($payload, 'transactionId')
            ?? data_get($payload, 'transactions.0.id')
            ?? data_get($payload, 'transactions.0.transactionId')
            ?? data_get($payload, 'integratorOrderId')
            ?? $payment->id
        );

        $updatedRows = PaymentRequest::where('id', $payment->id)
            ->where('is_paid', 0)
            ->update([
                'payment_method' => 'keepz',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

        if ($updatedRows !== 1) {
            return;
        }

        $updatedPayment = PaymentRequest::find($payment->id);
        if (!$updatedPayment) {
            return;
        }

        $metadata = $this->metadata($updatedPayment);
        $metadata['keepz_recovery_paid_at'] = now()->toIso8601String();
        $metadata['keepz_recovery_status'] = $this->safePayload($payload);
        $this->storeMetadata($updatedPayment, $metadata);

        if (function_exists($updatedPayment->success_hook)) {
            call_user_func($updatedPayment->success_hook, $updatedPayment);
        }
    }

    private function matchesPaymentRequest(PaymentRequest $payment, array $payload): bool
    {
        $actualAmount = data_get($payload, 'amount') ?? data_get($payload, 'acquiringAmount');
        $actualCurrency = data_get($payload, 'initialCurrency') ?? data_get($payload, 'currency');

        if ($actualAmount !== null && abs(((float) $actualAmount) - ((float) $payment->payment_amount)) > 0.01) {
            Log::error('Keepz recovery amount mismatch', [
                'payment_request_id' => $payment->id,
                'expected_amount' => $payment->payment_amount,
                'actual_amount' => $actualAmount,
            ]);
            return false;
        }

        if ($actualCurrency !== null
            && strtoupper((string) $actualCurrency) !== strtoupper((string) ($payment->currency_code ?? 'GEL'))) {
            Log::error('Keepz recovery currency mismatch', [
                'payment_request_id' => $payment->id,
                'expected_currency' => $payment->currency_code,
                'actual_currency' => $actualCurrency,
            ]);
            return false;
        }

        return true;
    }

    private function markTerminal(PaymentRequest $payment, ?string $status, ?array $payload = null): void
    {
        $metadata = $this->metadata($payment);
        $metadata['keepz_recovery_terminal_status'] = $status;
        $metadata['keepz_recovery_terminal_at'] = now()->toIso8601String();
        if ($payload !== null) {
            $metadata['keepz_recovery_terminal_payload'] = $this->safePayload($payload);
        }
        $this->storeMetadata($payment, $metadata);
    }

    private function metadata(PaymentRequest $payment): array
    {
        $metadata = json_decode($payment->additional_data ?? '[]', true);
        return is_array($metadata) ? $metadata : [];
    }

    private function storeMetadata(PaymentRequest $payment, array $metadata): void
    {
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        PaymentRequest::where('id', $payment->id)->update(['additional_data' => $encoded]);
        $payment->additional_data = $encoded;
    }

    private function safePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        unset($payload['encryptedData'], $payload['encryptedKeys']);
        unset($payload['urlForQR'], $payload['redirectUrl'], $payload['checkoutUrl'], $payload['url']);
        return $payload;
    }

    private function isConfigured(): bool
    {
        return !empty($this->configValues?->identifier)
            && !empty($this->configValues?->integrator_id)
            && !empty($this->configValues?->keepz_public_key)
            && !empty($this->configValues?->integrator_private_key);
    }

    private function sendEncryptedRequest(string $method, string $endpoint, array $payload): ?array
    {
        $envelope = $this->buildEncryptedEnvelope($payload);
        if ($envelope === null) {
            return null;
        }

        $method = strtoupper($method);
        $url = $this->baseUrl() . $endpoint;
        $body = null;

        if (in_array($method, ['GET', 'DELETE'], true)) {
            $queryEnvelope = $envelope;
            $queryEnvelope['aes'] = $envelope['aes'] ? 'true' : 'false';
            $url .= '?' . http_build_query($queryEnvelope, '', '&', PHP_QUERY_RFC3986);
        } else {
            $body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Log::warning('Keepz recovery request failed before response', [
                'endpoint' => $endpoint,
                'integrator_order_id' => $payload['integratorOrderId'] ?? null,
                'curl_error' => $curlError,
            ]);
            return null;
        }

        $decoded = json_decode($response ?: '', true);
        if (!is_array($decoded)) {
            Log::warning('Keepz recovery returned non JSON', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'body_preview' => Str::limit((string) $response, 300, ''),
            ]);
            return null;
        }

        return $this->decodeKeepzResponse($decoded);
    }

    private function buildEncryptedEnvelope(array $payload): ?array
    {
        try {
            $plainText = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $aesKey = random_bytes(32);
            $iv = random_bytes(16);

            $cipherText = openssl_encrypt($plainText, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
            if ($cipherText === false) {
                throw new \RuntimeException('AES encryption failed');
            }

            $aesProperties = base64_encode($aesKey) . '.' . base64_encode($iv);
            $publicKey = PublicKeyLoader::load($this->normalizePublicKey((string) $this->configValues->keepz_public_key))
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            return [
                'identifier' => trim((string) $this->configValues->identifier),
                'encryptedData' => base64_encode($cipherText),
                'encryptedKeys' => base64_encode($publicKey->encrypt($aesProperties)),
                'aes' => true,
            ];
        } catch (Throwable $exception) {
            Log::error('Keepz recovery encryption failed', [
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function decodeKeepzResponse(array $response): ?array
    {
        if (isset($response['encryptedData'], $response['encryptedKeys'])) {
            return $this->decryptEnvelope((string) $response['encryptedData'], (string) $response['encryptedKeys']);
        }

        return $response;
    }

    private function decryptEnvelope(string $encryptedData, string $encryptedKeys): ?array
    {
        try {
            $decodedKeys = base64_decode($encryptedKeys, true);
            $decodedData = base64_decode($encryptedData, true);
            if ($decodedKeys === false || $decodedData === false) {
                throw new \RuntimeException('Invalid Keepz response envelope');
            }

            $privateKey = PublicKeyLoader::loadPrivateKey(
                $this->normalizePrivateKey((string) $this->configValues->integrator_private_key)
            )
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            $aesProperties = $privateKey->decrypt($decodedKeys);
            $delimiterPosition = strpos($aesProperties, '.');
            if ($delimiterPosition === false) {
                throw new \RuntimeException('Invalid Keepz AES envelope');
            }

            $aesKey = base64_decode(substr($aesProperties, 0, $delimiterPosition), true);
            $iv = base64_decode(substr($aesProperties, $delimiterPosition + 1), true);
            if ($aesKey === false || $iv === false) {
                throw new \RuntimeException('Invalid Keepz AES key material');
            }

            $plainText = openssl_decrypt($decodedData, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
            if ($plainText === false) {
                throw new \RuntimeException('Keepz response decryption failed');
            }

            $decoded = json_decode($plainText, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $exception) {
            Log::error('Keepz recovery response decryption failed', [
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function normalizePublicKey(string $key): string
    {
        return $this->normalizeKey($key, 'PUBLIC KEY');
    }

    private function normalizePrivateKey(string $key): string
    {
        $normalized = $this->normalizeStoredKey($key);
        if (str_contains($normalized, '-----BEGIN')) {
            return $normalized;
        }
        return $this->normalizeKey($normalized, 'PRIVATE KEY');
    }

    private function normalizeKey(string $key, string $label): string
    {
        $normalized = $this->normalizeStoredKey($key);
        if (str_contains($normalized, '-----BEGIN')) {
            return $normalized;
        }

        $body = preg_replace('/\s+/', '', $normalized) ?? $normalized;
        return "-----BEGIN {$label}-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END {$label}-----";
    }

    private function normalizeStoredKey(string $key): string
    {
        $normalized = str_replace('\\n', "\n", trim($key));
        if (str_contains($normalized, '-----BEGIN') && substr_count($normalized, "\n") < 2) {
            if (preg_match('/(-----BEGIN [^-]+-----)(.*)(-----END [^-]+-----)/s', $normalized, $matches)) {
                $body = preg_replace('/\s+/', '', $matches[2]) ?? $matches[2];
                return $matches[1] . "\n" . chunk_split($body, 64, "\n") . $matches[3];
            }
        }
        return $normalized;
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }
        return strtolower(str_replace([' ', '-'], '_', trim($status)));
    }

    private function baseUrl(): string
    {
        return $this->isLiveMode ? self::LIVE_API_BASE_URL : self::TEST_API_BASE_URL;
    }

    private function result(
        string $state,
        ?string $providerStatus,
        ?string $error = null,
        ?array $payload = null
    ): array {
        return [
            'state' => $state,
            'provider_status' => $providerStatus,
            'error' => $error,
            'payload' => $payload,
        ];
    }

    private function logUnusableResponse(string $message, PaymentRequest $payment, ?array $payload): void
    {
        Log::warning($message, [
            'payment_request_id' => $payment->id,
            'status' => data_get($payload, 'status'),
            'status_code' => data_get($payload, 'statusCode'),
            'message' => data_get($payload, 'message'),
        ]);
    }
}
