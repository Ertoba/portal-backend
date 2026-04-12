<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FlittPaymentController extends Controller
{
    use Processor;

    private const API_BASE_URL = 'https://pay.flitt.com';
    private const API_VERSION = '1.0.1';

    private mixed $config_values;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('flitt', 'payment_config');

        if (!is_null($config) && $config->mode == 'live') {
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

        $payload = $this->buildCreateOrderPayload($payment);
        $response = $this->postToFlitt('/api/checkout/url', $payload);
        $checkoutUrl = data_get($response, 'response.checkout_url');

        if (data_get($response, 'response.response_status') === 'success' && !empty($checkoutUrl)) {
            return Redirect::away($checkoutUrl);
        }

        return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
    }

    public function callback(Request $request)
    {
        $payload = $this->extractBodyPayload($request);

        if (empty($payload) || !$this->isValidSignature($payload)) {
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payment = $this->payment::where(['id' => data_get($payload, 'order_id')])->first();

        if (!$payment) {
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

        $payload = $this->buildCreateOrderPayload($payment, true);
        $response = $this->postToFlitt('/api/checkout/token', $payload);
        $token = data_get($response, 'response.token');

        if (data_get($response, 'response.response_status') === 'success' && !empty($token)) {
            return response()->json([
                'payment_id' => (string) $payment->id,
                'merchant_id' => (int) $this->config_values->merchant_id,
                'token' => (string) $token,
            ]);
        }

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
            return response()->json(['message' => 'Payment request not found'], 404);
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

    private function isConfigured(): bool
    {
        return !empty($this->config_values?->merchant_id) && !empty($this->config_values?->secret_key);
    }

    private function buildCreateOrderPayload(PaymentRequest $payment, bool $mobileSdk = false): array
    {
        $payload = [
            'version' => self::API_VERSION,
            'order_id' => $payment->id,
            'merchant_id' => (string) $this->config_values->merchant_id,
            'order_desc' => Str::limit((string) ($payment->attribute ?? 'order_payment'), 1024, ''),
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
        curl_close($ch);

        $decoded = json_decode($response ?: '', true);

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
        return in_array($this->normalizeStatus($status), ['declined', 'expired', 'reversed'], true);
    }

    private function processFlittStatus(PaymentRequest $payment, array $payload, ?string $transactionId): ?string
    {
        $status = $this->normalizeStatus(data_get($payload, 'order_status'));

        if ($status === 'approved') {
            $updatedPayment = $this->finalizeSuccessfulPayment($payment, $transactionId);

            if ($updatedPayment) {
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
            call_user_func($payment->success_hook, $payment);
        }
    }

    private function callFailureHook(?PaymentRequest $payment): void
    {
        if (isset($payment) && function_exists($payment->failure_hook)) {
            call_user_func($payment->failure_hook, $payment);
        }
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
}
