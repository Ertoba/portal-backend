<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

class KeepzPaymentController extends Controller
{
    use Processor;

    private const TEST_API_BASE_URL = 'https://gateway.dev.keepz.me/ecommerce-service';
    private const LIVE_API_BASE_URL = 'https://gateway.keepz.me/ecommerce-service';
    private const SUPPORTED_CURRENCIES = ['GEL', 'USD', 'EUR'];

    private mixed $config_values;
    private PaymentRequest $payment;
    private bool $isLiveMode = false;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('keepz', 'payment_config');

        if (!is_null($config) && $config->mode == 'live') {
            $this->isLiveMode = true;
            $this->config_values = json_decode($config->live_values);
        } else {
            $this->config_values = !is_null($config) ? json_decode($config->test_values) : null;
        }

        $this->payment = $payment;
    }

    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();

        if (!isset($payment) || !$this->isConfigured() || !$this->isCurrencySupported($payment)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $response = $this->createKeepzOrder($payment);
        $redirectUrl = $this->redirectUrlFromResponse($response);

        if ($redirectUrl !== null) {
            $metadata = $this->getPaymentMetadata($payment);
            $metadata['keepz_order_created_at'] = now()->toIso8601String();
            $metadata['keepz_order_url_received'] = true;
            $metadata['keepz_create_response'] = $this->safeGatewayPayload($response);
            $this->updatePaymentMetadata($payment, $metadata);

            return response()
                ->view('payment-views.keepz-redirect', [
                    'payment' => $payment,
                    'redirectUrl' => $redirectUrl,
                    'amount' => number_format((float) $payment->payment_amount, 2),
                    'currency' => strtoupper($payment->currency_code ?? 'GEL'),
                    'title' => $this->checkoutTitle($payment),
                    'isLiveMode' => $this->isLiveMode,
                ])
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $this->logGatewayResponse('Keepz order was not created', $payment, $response);

        return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
    }

    public function success(Request $request)
    {
        $payment = $this->resolvePaymentFromRequest($request);

        if (!$payment) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($payment->is_paid) {
            return $this->payment_response($payment, 'success');
        }

        $statusPayload = $this->getOrderStatusPayload($payment);

        if ($statusPayload) {
            $status = $this->processKeepzStatus($payment, $statusPayload);
            $payment = $this->payment::where(['id' => $payment->id])->first() ?? $payment;

            if ($status === 'success' || $payment->is_paid) {
                return $this->payment_response($payment, 'success');
            }

            if ($this->isFailureStatus($status)) {
                return $this->payment_response($payment, 'fail');
            }
        }

        return $this->payment_response($payment, 'fail');
    }

    public function fail(Request $request)
    {
        $payment = $this->resolvePaymentFromRequest($request);

        if (!$payment) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($payment->is_paid) {
            return $this->payment_response($payment, 'success');
        }

        $statusPayload = $this->getOrderStatusPayload($payment);

        if ($statusPayload) {
            $status = $this->processKeepzStatus($payment, $statusPayload);
            $payment = $this->payment::where(['id' => $payment->id])->first() ?? $payment;

            if ($status === 'success' || $payment->is_paid) {
                return $this->payment_response($payment, 'success');
            }
        } else {
            $this->finalizeFailedPayment($payment, null, 'redirect_fail');
        }

        return $this->payment_response($payment, 'fail');
    }

    public function callback(Request $request)
    {
        $payload = $this->extractBodyPayload($request);
        $payment = $this->resolvePaymentFromRequest($request, $payload);

        if (!$payment) {
            Log::warning('Keepz callback payment request was not found', [
                'integrator_order_id' => data_get($payload, 'integratorOrderId'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'not_found'], 404);
        }

        $statusPayload = $this->getOrderStatusPayload($payment);

        if (!$statusPayload) {
            Log::warning('Keepz callback status verification failed', [
                'payment_request_id' => $payment->id,
                'callback_status' => data_get($payload, 'status'),
            ]);

            return response()->json(['status' => 'status_unavailable'], 503);
        }

        $status = $this->processKeepzStatus($payment, $statusPayload);

        return response()->json([
            'status' => 'ok',
            'payment_status' => $status,
        ]);
    }

    private function isConfigured(): bool
    {
        return !empty($this->config_values?->identifier)
            && !empty($this->config_values?->integrator_id)
            && !empty($this->config_values?->receiver_id)
            && !empty($this->config_values?->receiver_type)
            && !empty($this->config_values?->keepz_public_key)
            && !empty($this->config_values?->integrator_private_key);
    }

    private function isCurrencySupported(PaymentRequest $payment): bool
    {
        return in_array(strtoupper($payment->currency_code ?? 'GEL'), self::SUPPORTED_CURRENCIES, true);
    }

    private function createKeepzOrder(PaymentRequest $payment): ?array
    {
        return $this->sendEncryptedRequest('POST', '/api/integrator/order', $this->buildCreateOrderPayload($payment), $payment);
    }

    private function buildCreateOrderPayload(PaymentRequest $payment): array
    {
        return [
            'amount' => round((float) $payment->payment_amount, 2),
            'receiverId' => trim((string) $this->config_values->receiver_id),
            'receiverType' => strtoupper(trim((string) $this->config_values->receiver_type)),
            'integratorId' => trim((string) $this->config_values->integrator_id),
            'integratorOrderId' => (string) $payment->id,
            'currency' => strtoupper($payment->currency_code ?? 'GEL'),
            'successRedirectUri' => $this->paymentRoute('keepz.success', ['payment_id' => $payment->id]),
            'failRedirectUri' => $this->paymentRoute('keepz.fail', ['payment_id' => $payment->id]),
            'callbackUri' => $this->paymentRoute('keepz.callback', ['payment_id' => $payment->id]),
            'language' => 'KA',
        ];
    }

    private function getOrderStatusPayload(PaymentRequest $payment): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $response = $this->sendEncryptedRequest('GET', '/api/integrator/order/status', [
            'integratorId' => trim((string) $this->config_values->integrator_id),
            'integratorOrderId' => (string) $payment->id,
        ], $payment);

        if (!is_array($response) || isset($response['statusCode'])) {
            $this->logGatewayResponse('Keepz order status response was not usable', $payment, $response);
            return null;
        }

        return $response;
    }

    private function sendEncryptedRequest(string $method, string $endpoint, array $payload, ?PaymentRequest $payment = null): ?array
    {
        $envelope = $this->buildEncryptedEnvelope($payload);

        if ($envelope === null) {
            return null;
        }

        $method = strtoupper($method);
        $url = $this->baseUrl() . $endpoint;
        $postBody = null;

        if ($method === 'GET') {
            $queryEnvelope = $envelope;
            $queryEnvelope['aes'] = $envelope['aes'] ? 'true' : 'false';
            $url .= '?' . http_build_query($queryEnvelope, '', '&', PHP_QUERY_RFC3986);
        } else {
            $postBody = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Log::warning('Keepz request failed before response', [
                'endpoint' => $endpoint,
                'integrator_order_id' => $payload['integratorOrderId'] ?? null,
                'curl_error' => $curlError,
            ]);

            return null;
        }

        $decoded = json_decode($response ?: '', true);

        if (!is_array($decoded)) {
            Log::warning('Keepz returned a non JSON response', [
                'endpoint' => $endpoint,
                'integrator_order_id' => $payload['integratorOrderId'] ?? null,
                'http_code' => $httpCode,
                'body_preview' => Str::limit((string) $response, 300, ''),
            ]);

            return null;
        }

        if ($httpCode >= 400) {
            Log::warning('Keepz returned an HTTP error', [
                'endpoint' => $endpoint,
                'integrator_order_id' => $payload['integratorOrderId'] ?? null,
                'http_code' => $httpCode,
                'status_code' => data_get($decoded, 'statusCode'),
                'message' => data_get($decoded, 'message'),
            ]);
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
            $publicKey = PublicKeyLoader::load($this->normalizePublicKey((string) $this->config_values->keepz_public_key))
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            return [
                'identifier' => trim((string) $this->config_values->identifier),
                'encryptedData' => base64_encode($cipherText),
                'encryptedKeys' => base64_encode($publicKey->encrypt($aesProperties)),
                'aes' => true,
            ];
        } catch (Throwable $exception) {
            Log::error('Keepz request encryption failed', [
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
                throw new \RuntimeException('Invalid base64 response envelope');
            }

            $privateKey = PublicKeyLoader::loadPrivateKey($this->normalizePrivateKey((string) $this->config_values->integrator_private_key))
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            $aesProperties = $privateKey->decrypt($decodedKeys);
            $delimiterPosition = strpos($aesProperties, '.');

            if ($delimiterPosition === false) {
                throw new \RuntimeException('Invalid AES property envelope');
            }

            $aesKey = base64_decode(substr($aesProperties, 0, $delimiterPosition), true);
            $iv = base64_decode(substr($aesProperties, $delimiterPosition + 1), true);

            if ($aesKey === false || $iv === false) {
                throw new \RuntimeException('Invalid AES key material');
            }

            $plainText = openssl_decrypt($decodedData, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
            if ($plainText === false) {
                throw new \RuntimeException('AES decryption failed');
            }

            $decoded = json_decode($plainText, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $exception) {
            Log::error('Keepz response decryption failed', [
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

        return "-----BEGIN {$label}-----\n" . chunk_split($body, 64, "\n") . "-----END {$label}-----";
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

    private function extractBodyPayload(Request $request): array
    {
        $rawBody = trim((string) $request->getContent());

        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $bodyParameters = $request->request->all();

        return is_array($bodyParameters) ? $bodyParameters : [];
    }

    private function resolvePaymentFromRequest(Request $request, array $payload = []): ?PaymentRequest
    {
        $paymentId = $request->query('payment_id')
            ?? $request->input('payment_id')
            ?? data_get($payload, 'integratorOrderId')
            ?? data_get($payload, 'integrator_order_id')
            ?? data_get($payload, 'order.integratorOrderId');

        if (!is_string($paymentId) || !Str::isUuid($paymentId)) {
            return null;
        }

        return $this->payment::where(['id' => $paymentId])->first();
    }

    private function paymentRoute(string $route, array $parameters = []): string
    {
        $url = route($route, $parameters, true);

        return preg_replace('/^http:\/\//i', 'https://', $url) ?? $url;
    }

    private function baseUrl(): string
    {
        return $this->isLiveMode ? self::LIVE_API_BASE_URL : self::TEST_API_BASE_URL;
    }

    private function redirectUrlFromResponse(?array $response): ?string
    {
        $url = data_get($response, 'urlForQR')
            ?? data_get($response, 'redirectUrl')
            ?? data_get($response, 'checkoutUrl')
            ?? data_get($response, 'url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function checkoutTitle(PaymentRequest $payment): string
    {
        $metadata = $this->getPaymentMetadata($payment);
        $businessName = data_get($metadata, 'business_name');

        return is_string($businessName) && $businessName !== '' ? $businessName : 'Mili';
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        return strtolower(str_replace([' ', '-'], '_', trim($status)));
    }

    private function isFailureStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['failed', 'failure', 'canceled', 'cancelled', 'expired'], true);
    }

    private function processKeepzStatus(PaymentRequest $payment, array $payload): ?string
    {
        $status = $this->normalizeStatus(data_get($payload, 'status') ?? data_get($payload, 'orderStatus'));

        if ($status === 'success') {
            if (!$this->matchesPaymentRequest($payment, $payload)) {
                return 'amount_or_currency_mismatch';
            }

            $updatedPayment = $this->finalizeSuccessfulPayment($payment, $payload);

            if ($updatedPayment) {
                $this->callSuccessHook($updatedPayment);
            }

            return $status;
        }

        if ($this->isFailureStatus($status)) {
            $failedPayment = $this->finalizeFailedPayment($payment, $this->transactionIdFromPayload($payment, $payload), $status);

            if ($failedPayment) {
                $this->callFailureHook($failedPayment);
            }
        }

        return $status;
    }

    private function finalizeSuccessfulPayment(PaymentRequest $payment, array $payload): ?PaymentRequest
    {
        $transactionId = $this->transactionIdFromPayload($payment, $payload);

        $updatedRows = $this->payment::where(['id' => $payment->id])
            ->where(['is_paid' => 0])
            ->update([
                'payment_method' => 'keepz',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

        if ($updatedRows !== 1) {
            return null;
        }

        $updatedPayment = $this->payment::where(['id' => $payment->id])->first();

        if ($updatedPayment) {
            $metadata = $this->getPaymentMetadata($updatedPayment);
            $metadata['keepz_paid_at'] = now()->toIso8601String();
            $metadata['keepz_status_response'] = $this->safeGatewayPayload($payload);
            $this->updatePaymentMetadata($updatedPayment, $metadata);
        }

        return $updatedPayment;
    }

    private function finalizeFailedPayment(PaymentRequest $payment, ?string $transactionId, ?string $status): ?PaymentRequest
    {
        if ($payment->is_paid) {
            return null;
        }

        $metadata = $this->getPaymentMetadata($payment);

        if (!empty($metadata['keepz_failure_handled'])) {
            return null;
        }

        $metadata['keepz_failure_handled'] = now()->toIso8601String();
        $metadata['keepz_failure_status'] = $status;
        $this->updatePaymentMetadata($payment, $metadata);

        if ($transactionId && empty($payment->transaction_id)) {
            $this->payment::where(['id' => $payment->id])->update([
                'transaction_id' => $transactionId,
            ]);
        }

        return $this->payment::where(['id' => $payment->id])->first() ?? $payment;
    }

    private function transactionIdFromPayload(PaymentRequest $payment, array $payload): string
    {
        $transactionId = data_get($payload, 'orderId')
            ?? data_get($payload, 'transactionId')
            ?? data_get($payload, 'transactions.0.id')
            ?? data_get($payload, 'transactions.0.transactionId')
            ?? data_get($payload, 'integratorOrderId');

        return (string) ($transactionId ?: $payment->id);
    }

    private function matchesPaymentRequest(PaymentRequest $payment, array $payload): bool
    {
        $actualAmount = data_get($payload, 'amount') ?? data_get($payload, 'acquiringAmount');
        $actualCurrency = data_get($payload, 'initialCurrency') ?? data_get($payload, 'currency');

        if ($actualAmount !== null && abs(((float) $actualAmount) - ((float) $payment->payment_amount)) > 0.01) {
            Log::error('Keepz successful payment amount mismatch', [
                'payment_request_id' => $payment->id,
                'expected_amount' => $payment->payment_amount,
                'actual_amount' => $actualAmount,
            ]);

            return false;
        }

        if ($actualCurrency !== null && strtoupper((string) $actualCurrency) !== strtoupper($payment->currency_code ?? 'GEL')) {
            Log::error('Keepz successful payment currency mismatch', [
                'payment_request_id' => $payment->id,
                'expected_currency' => $payment->currency_code,
                'actual_currency' => $actualCurrency,
            ]);

            return false;
        }

        return true;
    }

    private function callSuccessHook(PaymentRequest $payment): void
    {
        if (function_exists($payment->success_hook)) {
            try {
                call_user_func($payment->success_hook, $payment);
            } catch (Throwable $exception) {
                Log::error('Keepz success hook failed', [
                    'payment_id' => $payment->id,
                    'hook' => $payment->success_hook,
                    'exception' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }
    }

    private function callFailureHook(?PaymentRequest $payment): void
    {
        if (isset($payment) && function_exists($payment->failure_hook)) {
            try {
                call_user_func($payment->failure_hook, $payment);
            } catch (Throwable $exception) {
                Log::error('Keepz failure hook failed', [
                    'payment_id' => $payment->id,
                    'hook' => $payment->failure_hook,
                    'exception' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }
    }

    private function getPaymentMetadata(PaymentRequest $payment): array
    {
        $metadata = json_decode($payment->additional_data ?? '[]', true);

        return is_array($metadata) ? $metadata : [];
    }

    private function updatePaymentMetadata(PaymentRequest $payment, array $metadata): void
    {
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->payment::where(['id' => $payment->id])->update([
            'additional_data' => $encodedMetadata,
        ]);

        $payment->additional_data = $encodedMetadata;
    }

    private function safeGatewayPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        unset($payload['encryptedData'], $payload['encryptedKeys']);
        unset($payload['urlForQR'], $payload['redirectUrl'], $payload['checkoutUrl'], $payload['url']);

        return $payload;
    }

    private function logGatewayResponse(string $message, PaymentRequest $payment, ?array $response): void
    {
        Log::warning($message, [
            'payment_request_id' => $payment->id,
            'payment_amount' => $payment->payment_amount,
            'currency' => $payment->currency_code,
            'status' => data_get($response, 'status'),
            'status_code' => data_get($response, 'statusCode'),
            'message' => data_get($response, 'message'),
        ]);
    }
}
