<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\FlittSavedCard;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class FlittPaymentController extends Controller
{
    use Processor;

    private const API_BASE_URL = 'https://pay.flitt.com';
    private const API_VERSION = '1.0.1';

    private mixed $config_values;
    private PaymentRequest $payment;
    private bool $isLiveMode = false;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('flitt', 'payment_config');

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

        if (!isset($payment) || !$this->isConfigured()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $saveCardRequested = $this->resolveSaveCardPreference($payment, $request);
        $payload = $this->buildCreateOrderPayload($payment, false, $saveCardRequested);
        $response = $this->postToFlitt('/api/checkout/token', $payload);
        $token = data_get($response, 'response.token');

        if (data_get($response, 'response.response_status') === 'success' && !empty($token)) {
            return response()
                ->view('payment-views.flitt-embedded', [
                    'payment' => $payment,
                    'token' => (string) $token,
                    'merchantId' => (int) $this->config_values->merchant_id,
                    'amount' => number_format((float) $payment->payment_amount, 2),
                    'currency' => strtoupper($payment->currency_code ?? 'GEL'),
                    'title' => $this->checkoutTitle($payment),
                    'isLiveMode' => $this->isLiveMode,
                    'canSaveCard' => $this->canSaveCard($payment),
                    'saveCardRequested' => $saveCardRequested,
                ])
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $this->logGatewayResponse('Flitt checkout token was not created', $payment, $response);

        return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
    }

    public function callback(Request $request)
    {
        $payload = $this->extractBodyPayload($request);

        if (empty($payload) || !$this->isValidSignature($payload)) {
            Log::warning('Flitt callback rejected', [
                'reason' => empty($payload) ? 'empty_payload' : 'invalid_signature',
                'order_id' => data_get($payload, 'order_id'),
                'payment_id' => data_get($payload, 'payment_id'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payment = $this->payment::where(['id' => data_get($payload, 'order_id')])->first();

        if (!$payment) {
            Log::warning('Flitt callback payment request was not found', [
                'order_id' => data_get($payload, 'order_id'),
                'payment_id' => data_get($payload, 'payment_id'),
            ]);

            return response()->json(['status' => 'not_found'], 404);
        }

        $this->processFlittStatus(
            payment: $payment,
            payload: $payload,
            transactionId: data_get($payload, 'payment_id')
        );

        return response()->json(['status' => 'ok']);
    }

    public function response(Request $request)
    {
        $payload = $this->extractBodyPayload($request);
        $paymentId = $request->query('payment_id') ?? data_get($payload, 'order_id');
        $payment = $this->payment::where(['id' => $paymentId])->first();

        if (!isset($payment)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($payment->is_paid) {
            return $this->payment_response($payment, 'success');
        }

        if (!empty($payload) && $this->isValidSignature($payload)) {
            $status = $this->processFlittStatus(
                payment: $payment,
                payload: $payload,
                transactionId: data_get($payload, 'payment_id')
            );

            if ($status === 'approved') {
                $payment = $this->payment::where(['id' => $payment->id])->first() ?? $payment;
                return $this->payment_response($payment, 'success');
            }

            if ($this->isFailureStatus($status)) {
                return $this->payment_response($payment, 'fail');
            }
        }

        $statusPayload = $this->getOrderStatusPayload($payment);
        if ($statusPayload) {
            $status = $this->processFlittStatus(
                payment: $payment,
                payload: $statusPayload,
                transactionId: data_get($statusPayload, 'payment_id')
            );

            $payment = $this->payment::where(['id' => $payment->id])->first() ?? $payment;

            if ($status === 'approved') {
                return $this->payment_response($payment, 'success');
            }
        }

        return $this->payment_response($payment, 'fail');
    }

    public function mobileIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        return $this->mobileIntentById((string) $request['payment_id']);
    }

    public function mobileIntentById(string $paymentId)
    {
        $payment = $this->payment::where(['id' => $paymentId])->where(['is_paid' => 0])->first();

        if (!isset($payment) || !$this->isConfigured()) {
            return response()->json(['message' => 'Flitt მობილური გადახდა მიუწვდომელია'], 404);
        }

        $saveCardRequested = $this->resolveSaveCardPreference($payment, request());
        $payload = $this->buildCreateOrderPayload($payment, true, $saveCardRequested);
        $response = $this->postToFlitt('/api/checkout/token', $payload);
        $token = data_get($response, 'response.token');

        if (data_get($response, 'response.response_status') === 'success' && !empty($token)) {
            return response()->json([
                'payment_id' => (string) $payment->id,
                'merchant_id' => (int) $this->config_values->merchant_id,
                'token' => (string) $token,
            ]);
        }

        $this->logGatewayResponse('Flitt mobile checkout token was not created', $payment, $response);

        return response()->json(['message' => 'Flitt გადახდის ინიციალიზაცია ვერ მოხერხდა'], 422);
    }

    public function mobileReturn(Request $request)
    {
        $payload = $this->extractBodyPayload($request);
        $paymentId = $request->query('payment_id') ?? data_get($payload, 'order_id');
        $payment = $paymentId ? $this->payment::where(['id' => $paymentId])->first() : null;

        if ($payment && !empty($payload) && $this->isValidSignature($payload)) {
            $this->processFlittStatus(
                payment: $payment,
                payload: $payload,
                transactionId: data_get($payload, 'payment_id')
            );
        }

        return response(
            '<!doctype html><html lang="ka"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Flitt</title></head><body style="margin:0;background:#f7f7f7;"></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public function mobileStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment = $this->payment::where(['id' => $request['payment_id']])->first();

        if (!$payment || !$this->isConfigured()) {
            return response()->json(['message' => 'გადახდის მოთხოვნა ვერ მოიძებნა'], 404);
        }

        if ($payment->is_paid) {
            return response()->json([
                'payment_id' => (string) $payment->id,
                'status' => 'approved',
                'is_paid' => true,
                'transaction_id' => $payment->transaction_id,
            ]);
        }

        $status = 'processing';
        $statusPayload = $this->getOrderStatusPayload($payment);
        if ($statusPayload) {
            $status = $this->processFlittStatus(
                payment: $payment,
                payload: $statusPayload,
                transactionId: data_get($statusPayload, 'payment_id')
            ) ?? 'processing';

            $payment = $this->payment::where(['id' => $payment->id])->first() ?? $payment;
        }

        return response()->json([
            'payment_id' => (string) $payment->id,
            'status' => $status,
            'is_paid' => (bool) $payment->is_paid,
            'transaction_id' => $payment->transaction_id,
        ]);
    }

    public function reconcilePaymentRequest(PaymentRequest $payment): ?string
    {
        if ($payment->is_paid || !$this->isConfigured()) {
            return $payment->is_paid ? 'approved' : null;
        }

        $statusPayload = $this->getOrderStatusPayload($payment);

        if (!$statusPayload) {
            return null;
        }

        return $this->processFlittStatus(
            payment: $payment,
            payload: $statusPayload,
            transactionId: data_get($statusPayload, 'payment_id')
        );
    }

    private function isConfigured(): bool
    {
        return !empty($this->config_values?->merchant_id) && !empty($this->config_values?->secret_key);
    }

    private function buildCreateOrderPayload(PaymentRequest $payment, bool $mobileSdk = false, bool $saveCardRequested = false): array
    {
        $payload = [
            'version' => self::API_VERSION,
            'order_id' => $payment->id,
            'merchant_id' => (string) $this->config_values->merchant_id,
            'order_desc' => $this->orderDescription($payment),
            'amount' => $this->toMinorAmount($payment->payment_amount),
            'currency' => strtoupper($payment->currency_code ?? 'GEL'),
            'response_url' => $this->paymentRoute(
                $mobileSdk ? 'flitt.mobile-return' : 'flitt.response',
                ['payment_id' => $payment->id]
            ),
            'server_callback_url' => $this->paymentRoute('flitt.callback'),
            'merchant_data' => $payment->id,
            'delayed' => 'N',
            'lang' => 'ka',
        ];

        if ($saveCardRequested) {
            $payload['required_rectoken'] = 'Y';
        }

        $email = $this->payerEmail($payment);
        if ($email !== null) {
            $payload['sender_email'] = $email;
        }

        $clientIp = request()?->ip();
        if (is_string($clientIp) && filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $payload['client_ip'] = $clientIp;
        }

        $payload['signature'] = $this->generateSignature($payload);

        return $payload;
    }

    private function getOrderStatusPayload(PaymentRequest $payment): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $payload = [
            'version' => self::API_VERSION,
            'order_id' => $payment->id,
            'merchant_id' => (string) $this->config_values->merchant_id,
        ];

        $payload['signature'] = $this->generateSignature($payload);

        $response = $this->postToFlitt('/api/status/order_id', $payload);
        $responsePayload = data_get($response, 'response');

        if (!is_array($responsePayload) || data_get($responsePayload, 'response_status') !== 'success' || !$this->isValidSignature($responsePayload)) {
            $this->logGatewayResponse('Flitt order status response was not usable', $payment, $response);
            return null;
        }

        return $responsePayload;
    }

    private function postToFlitt(string $endpoint, array $payload): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_BASE_URL . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            Log::warning('Flitt request failed before response', [
                'endpoint' => $endpoint,
                'order_id' => $payload['order_id'] ?? null,
                'curl_error' => $curlError,
            ]);

            return null;
        }

        $decoded = json_decode($response ?: '', true);

        if (!is_array($decoded)) {
            Log::warning('Flitt returned a non JSON response', [
                'endpoint' => $endpoint,
                'order_id' => $payload['order_id'] ?? null,
                'http_code' => $httpCode,
                'body_preview' => Str::limit((string) $response, 300, ''),
            ]);

            return null;
        }

        if ($httpCode >= 400) {
            Log::warning('Flitt returned an HTTP error', [
                'endpoint' => $endpoint,
                'order_id' => $payload['order_id'] ?? null,
                'http_code' => $httpCode,
                'response_status' => data_get($decoded, 'response.response_status'),
                'response_code' => data_get($decoded, 'response.response_code'),
                'response_description' => data_get($decoded, 'response.response_description'),
            ]);
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function extractBodyPayload(Request $request): array
    {
        $rawBody = trim((string) $request->getContent());

        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);

            if (is_array($decoded)) {
                return $this->normalizeResponsePayload($decoded);
            }
        }

        $bodyParameters = $request->request->all();

        return is_array($bodyParameters) ? $this->normalizeResponsePayload($bodyParameters) : [];
    }

    private function normalizeResponsePayload(array $payload): array
    {
        if (isset($payload['response']) && is_array($payload['response'])) {
            return $payload['response'];
        }

        return $payload;
    }

    private function paymentRoute(string $route, array $parameters = []): string
    {
        $url = route($route, $parameters, true);

        return preg_replace('/^http:\/\//i', 'https://', $url) ?? $url;
    }

    private function toMinorAmount(float|string|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function checkoutTitle(PaymentRequest $payment): string
    {
        $metadata = $this->getPaymentMetadata($payment);
        $businessName = data_get($metadata, 'business_name');

        return is_string($businessName) && $businessName !== '' ? $businessName : 'გადახდა';
    }

    private function orderDescription(PaymentRequest $payment): string
    {
        $parts = ['MILI'];

        if (!empty($payment->attribute)) {
            $parts[] = (string) $payment->attribute;
        }

        if (!empty($payment->attribute_id)) {
            $parts[] = '#' . $payment->attribute_id;
        }

        return Str::limit(implode(' ', $parts), 1024, '');
    }

    private function payerEmail(PaymentRequest $payment): ?string
    {
        $payer = json_decode($payment->payer_information ?? '[]', true);
        $email = is_array($payer) ? data_get($payer, 'email') : null;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function generateSignature(array $params): string
    {
        unset($params['signature'], $params['response_signature_string']);
        ksort($params);

        $signatureParts = [$this->config_values->secret_key];

        foreach ($params as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $signatureParts[] = $value ? '1' : '0';
                continue;
            }

            $signatureParts[] = (string) $value;
        }

        return sha1(implode('|', $signatureParts));
    }

    private function isValidSignature(array $params): bool
    {
        $signature = (string) data_get($params, 'signature', '');

        if ($signature === '' || !$this->isConfigured()) {
            return false;
        }

        return hash_equals($signature, $this->generateSignature($params));
    }

    private function normalizeStatus(?string $status): ?string
    {
        return $status ? strtolower(trim($status)) : null;
    }

    private function isFailureStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['declined', 'expired', 'reversed', 'canceled', 'cancelled', 'failed', 'failure'], true);
    }

    private function processFlittStatus(PaymentRequest $payment, array $payload, ?string $transactionId): ?string
    {
        $status = $this->normalizeStatus(data_get($payload, 'order_status'));

        if ($status === 'approved') {
            if (!$this->matchesPaymentRequest($payment, $payload)) {
                return 'amount_or_currency_mismatch';
            }

            $updatedPayment = $this->finalizeSuccessfulPayment($payment, $transactionId);

            if ($updatedPayment) {
                $this->storeSavedCardIfRequested($updatedPayment, $payload);
                $this->callSuccessHook($updatedPayment);
            }

            return $status;
        }

        if ($this->isFailureStatus($status)) {
            $failedPayment = $this->finalizeFailedPayment($payment, $transactionId, $status);

            if ($failedPayment) {
                $this->callFailureHook($failedPayment);
            }
        }

        return $status;
    }

    private function finalizeSuccessfulPayment(PaymentRequest $payment, ?string $transactionId): ?PaymentRequest
    {
        $updatedRows = $this->payment::where(['id' => $payment->id])
            ->where(['is_paid' => 0])
            ->update([
                'payment_method' => 'flitt',
                'is_paid' => 1,
                'transaction_id' => $transactionId ?? $payment->transaction_id,
            ]);

        if ($updatedRows !== 1) {
            return null;
        }

        return $this->payment::where(['id' => $payment->id])->first();
    }

    private function finalizeFailedPayment(PaymentRequest $payment, ?string $transactionId, ?string $status): ?PaymentRequest
    {
        if ($payment->is_paid) {
            return null;
        }

        $metadata = $this->getPaymentMetadata($payment);

        if (!empty($metadata['flitt_failure_handled'])) {
            return null;
        }

        $metadata['flitt_failure_handled'] = now()->toIso8601String();
        $metadata['flitt_failure_status'] = $status;
        $this->updatePaymentMetadata($payment, $metadata);

        if ($transactionId && empty($payment->transaction_id)) {
            $this->payment::where(['id' => $payment->id])->update([
                'transaction_id' => $transactionId,
            ]);
        }

        return $this->payment::where(['id' => $payment->id])->first() ?? $payment;
    }

    private function callSuccessHook(PaymentRequest $payment): void
    {
        if (function_exists($payment->success_hook)) {
            try {
                call_user_func($payment->success_hook, $payment);
            } catch (Throwable $exception) {
                Log::error('Flitt success hook failed', [
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
                Log::error('Flitt failure hook failed', [
                    'payment_id' => $payment->id,
                    'hook' => $payment->failure_hook,
                    'exception' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }
    }

    private function matchesPaymentRequest(PaymentRequest $payment, array $payload): bool
    {
        $expectedAmount = $this->toMinorAmount($payment->payment_amount);
        $actualAmount = data_get($payload, 'amount');
        $expectedCurrency = strtoupper($payment->currency_code ?? 'GEL');
        $actualCurrency = strtoupper((string) data_get($payload, 'currency', $expectedCurrency));

        if ($actualAmount !== null && (int) $actualAmount !== $expectedAmount) {
            Log::error('Flitt approved payment amount mismatch', [
                'payment_request_id' => $payment->id,
                'flitt_payment_id' => data_get($payload, 'payment_id'),
                'expected_amount' => $expectedAmount,
                'actual_amount' => $actualAmount,
            ]);

            return false;
        }

        if ($actualCurrency !== $expectedCurrency) {
            Log::error('Flitt approved payment currency mismatch', [
                'payment_request_id' => $payment->id,
                'flitt_payment_id' => data_get($payload, 'payment_id'),
                'expected_currency' => $expectedCurrency,
                'actual_currency' => $actualCurrency,
            ]);

            return false;
        }

        return true;
    }

    private function logGatewayResponse(string $message, PaymentRequest $payment, ?array $response): void
    {
        Log::warning($message, [
            'payment_request_id' => $payment->id,
            'payment_amount' => $payment->payment_amount,
            'currency' => $payment->currency_code,
            'response_status' => data_get($response, 'response.response_status'),
            'response_code' => data_get($response, 'response.response_code'),
            'response_description' => data_get($response, 'response.response_description'),
        ]);
    }

    private function getPaymentMetadata(PaymentRequest $payment): array
    {
        $metadata = json_decode($payment->additional_data ?? '[]', true);

        return is_array($metadata) ? $metadata : [];
    }

    private function updatePaymentMetadata(PaymentRequest $payment, array $metadata): void
    {
        $encodedMetadata = json_encode($metadata);

        $this->payment::where(['id' => $payment->id])->update([
            'additional_data' => $encodedMetadata,
        ]);

        $payment->additional_data = $encodedMetadata;
    }

    private function canSaveCard(PaymentRequest $payment): bool
    {
        return is_numeric($payment->payer_id)
            && (int) $payment->payer_id > 0
            && $this->normalizeStatus((string) $payment->attribute) === 'order';
    }

    private function resolveSaveCardPreference(PaymentRequest $payment, Request $request): bool
    {
        if (!$this->canSaveCard($payment)) {
            return false;
        }

        $metadata = $this->getPaymentMetadata($payment);
        $requested = $request->has('save_card')
            ? $request->boolean('save_card')
            : (bool) data_get($metadata, 'flitt_save_card_requested', false);

        $metadata['flitt_save_card_requested'] = $requested;
        $metadata['flitt_save_card_requested_at'] = $requested ? now()->toIso8601String() : null;
        $this->updatePaymentMetadata($payment, $metadata);

        return $requested;
    }

    private function storeSavedCardIfRequested(PaymentRequest $payment, array $payload): void
    {
        $metadata = $this->getPaymentMetadata($payment);

        if (empty($metadata['flitt_save_card_requested']) || !$this->canSaveCard($payment)) {
            return;
        }

        $rectoken = (string) data_get($payload, 'rectoken', '');

        if ($rectoken === '') {
            return;
        }

        $additionalInfo = data_get($payload, 'additional_info');
        $additionalInfo = is_string($additionalInfo) ? json_decode($additionalInfo, true) : [];
        $additionalInfo = is_array($additionalInfo) ? $additionalInfo : [];

        FlittSavedCard::updateOrCreate(
            [
                'customer_id' => (int) $payment->payer_id,
                'gateway' => 'flitt',
                'rectoken' => $rectoken,
            ],
            [
                'masked_card' => data_get($payload, 'masked_card') ?: null,
                'card_type' => data_get($payload, 'card_type') ?: data_get($additionalInfo, 'card_type'),
                'card_bin' => data_get($payload, 'card_bin') ?: null,
                'rectoken_lifetime' => data_get($payload, 'rectoken_lifetime') ?: null,
                'is_active' => true,
                'metadata' => [
                    'payment_request_id' => (string) $payment->id,
                    'flitt_payment_id' => data_get($payload, 'payment_id'),
                    'payment_system' => data_get($payload, 'payment_system'),
                    'saved_at' => now()->toIso8601String(),
                ],
            ]
        );

        $metadata['flitt_card_saved_at'] = now()->toIso8601String();
        $metadata['flitt_card_saved_masked'] = data_get($payload, 'masked_card') ?: null;
        $this->updatePaymentMetadata($payment, $metadata);
    }
}
