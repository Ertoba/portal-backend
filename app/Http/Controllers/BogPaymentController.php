<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BogPaymentController extends Controller
{
    use Processor;

    private const CALLBACK_PUBLIC_KEY = <<<'KEY'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqczfAuhtxw2iF68kS0Hy
bGSv0ZlDAjsXh6VC8avDl3Vxa9qCn6Pzl37Tl2Z21WodiISLeXdhCtOMTeLNUBeb
CYD31y2/MwnhLYqlCk2bOh29fyPc1iT5Eu/k/1IaNRrK9/UVZaTkhOMeEm+aL4y8
5XsE4UjqftEmwrAdbO2G4cCpuoMC9ZXG9gAdr2BFN6i2Vt9eCen5Poj7E1ik7s8T
GyzploVV0NflhwBGeWnvQANUQGr87gsP5k2JG1z5EwnMybJQ7i3XT726rJMaV6QW
sY5hP72Mtv1I1zL2d9FXm9FWOzbpcXCyxuEBXvqqOHzogri8C7KRRYKyk97Ri7D6
8wIDAQAB
-----END PUBLIC KEY-----
KEY;

    private mixed $config_values;
    private string $token_url;
    private string $orders_url;
    private string $receipt_url;

    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('bog_pay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
            $this->token_url = 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token';
            $this->orders_url = 'https://api.bog.ge/payments/v1/ecommerce/orders';
            $this->receipt_url = 'https://api.bog.ge/payments/v1/receipt/';
        } else {
            $this->config_values = !is_null($config) ? json_decode($config->test_values) : null;
            $this->token_url = 'https://oauth2-sandbox.bog.ge/auth/realms/bog/protocol/openid-connect/token';
            $this->orders_url = 'https://api-sandbox.bog.ge/payments/v1/ecommerce/orders';
            $this->receipt_url = 'https://api-sandbox.bog.ge/payments/v1/receipt/';
        }

        $this->payment = $payment;
    }

    private function getAccessToken(): ?string
    {
        if (
            empty($this->config_values)
            || empty($this->config_values->client_id)
            || empty($this->config_values->client_secret)
        ) {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $this->config_values->client_id . ':' . $this->config_values->client_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return $data['access_token'] ?? null;
    }

    private function paymentRoute(string $route, array $parameters = []): string
    {
        $url = route($route, $parameters, true);

        return preg_replace('/^http:\/\//i', 'https://', $url) ?? $url;
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

    private function resolveIdempotencyKey(PaymentRequest $payment): string
    {
        $metadata = $this->getPaymentMetadata($payment);

        if (!empty($metadata['bog_idempotency_key'])) {
            return $metadata['bog_idempotency_key'];
        }

        $metadata['bog_idempotency_key'] = Str::uuid()->toString();
        $this->updatePaymentMetadata($payment, $metadata);

        return $metadata['bog_idempotency_key'];
    }

    private function maskEmail(?string $email): ?string
    {
        if (empty($email) || strpos($email, '@') === false) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 1);
        $maskedLength = max(strlen($name) - 1, 2);

        return $visible . str_repeat('*', $maskedLength) . '@' . $domain;
    }

    private function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $trimmedPhone = trim($phone);
        $length = strlen($trimmedPhone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($trimmedPhone, 0, 3) . str_repeat('*', max($length - 7, 2)) . substr($trimmedPhone, -4);
    }

    private function buildBuyerPayload(PaymentRequest $payment): array
    {
        $payerInformation = json_decode($payment->payer_information ?? '[]', true);
        if (!is_array($payerInformation)) {
            return [];
        }

        $buyer = [];

        if (!empty($payerInformation['name'])) {
            $buyer['full_name'] = $payerInformation['name'];
        }

        if (!empty($payerInformation['email'])) {
            $maskedEmail = $this->maskEmail($payerInformation['email']);
            if ($maskedEmail) {
                $buyer['masked_email'] = $maskedEmail;
            }
        }

        if (!empty($payerInformation['phone'])) {
            $maskedPhone = $this->maskPhone($payerInformation['phone']);
            if ($maskedPhone) {
                $buyer['masked_phone'] = $maskedPhone;
            }
        }

        return $buyer;
    }

    private function resolveApplicationType(?string $paymentPlatform): ?string
    {
        if ($paymentPlatform === 'web') {
            return 'web';
        }

        if (in_array($paymentPlatform, ['app', 'mobile'], true)) {
            return 'mobile';
        }

        return null;
    }

    private function decodeCallbackSignature(?string $signatureHeader): ?string
    {
        if (empty($signatureHeader)) {
            return null;
        }

        $normalizedSignature = preg_replace('/\s+/', '', trim($signatureHeader));
        $normalizedSignature = strtr($normalizedSignature, '-_', '+/');
        $paddingLength = strlen($normalizedSignature) % 4;

        if ($paddingLength > 0) {
            $normalizedSignature .= str_repeat('=', 4 - $paddingLength);
        }

        $decodedSignature = base64_decode($normalizedSignature, true);

        return $decodedSignature === false ? null : $decodedSignature;
    }

    private function verifyCallbackSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (empty($rawBody) || empty($signatureHeader) || !function_exists('openssl_verify')) {
            return false;
        }

        $decodedSignature = $this->decodeCallbackSignature($signatureHeader);
        if ($decodedSignature === null) {
            return false;
        }

        $publicKey = openssl_pkey_get_public(self::CALLBACK_PUBLIC_KEY);
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($rawBody, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function finalizeSuccessfulPayment(PaymentRequest $payment, ?string $transactionId): ?PaymentRequest
    {
        $updatedRows = $this->payment::where(['id' => $payment->id])
            ->where(['is_paid' => 0])
            ->update([
                'payment_method' => 'bog_pay',
                'is_paid' => 1,
                'transaction_id' => $transactionId ?? $payment->transaction_id,
            ]);

        if ($updatedRows !== 1) {
            return null;
        }

        return $this->payment::where(['id' => $payment->id])->first();
    }

    private function isFailureStatus(?string $statusKey): bool
    {
        return $statusKey === 'rejected';
    }

    private function finalizeFailedPayment(PaymentRequest $payment, ?string $transactionId, ?string $statusKey = null): ?PaymentRequest
    {
        if ($payment->is_paid) {
            return null;
        }

        $metadata = $this->getPaymentMetadata($payment);

        if (!empty($metadata['bog_failure_handled'])) {
            return null;
        }

        $metadata['bog_failure_handled'] = now()->toIso8601String();

        if ($statusKey) {
            $metadata['bog_failure_status'] = $statusKey;
        }

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

    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $body = [
            'callback_url' => $this->paymentRoute('bog.callback'),
            'external_order_id' => $data->id,
            'capture' => 'automatic',
            'purchase_units' => [
                'currency' => $data->currency_code ?? 'GEL',
                'total_amount' => (float) $data->payment_amount,
                'basket' => [
                    [
                        'product_id' => $data->id,
                        'description' => $data->attribute ?? 'order_payment',
                        'quantity' => 1,
                        'unit_price' => (float) $data->payment_amount,
                        'total_price' => (float) $data->payment_amount,
                    ],
                ],
            ],
            'redirect_urls' => [
                'success' => $this->paymentRoute('bog.success', ['payment_id' => $data->id]),
                'fail' => $this->paymentRoute('bog.fail', ['payment_id' => $data->id]),
            ],
        ];

        $buyer = $this->buildBuyerPayload($data);
        if (!empty($buyer)) {
            $body['buyer'] = $buyer;
        }

        $applicationType = $this->resolveApplicationType($data->payment_platform);
        if ($applicationType) {
            $body['application_type'] = $applicationType;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->orders_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Language: ka',
            'Authorization: Bearer ' . $accessToken,
            'Idempotency-Key: ' . $idempotencyKey,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response);

        if (isset($result->_links->redirect->href)) {
            $this->payment::where(['id' => $data->id])->update([
                'transaction_id' => $result->id,
            ]);

            return Redirect::away($result->_links->redirect->href);
        }

        return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
    }

    public function callback(Request $request)
    {
        $rawBody = $request->getContent();
        if (!$this->verifyCallbackSignature($rawBody, $request->header('Callback-Signature'))) {
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || ($payload['event'] ?? null) !== 'order_payment' || !isset($payload['body']) || !is_array($payload['body'])) {
            return response()->json(['status' => 'error'], 400);
        }

        $callbackBody = $payload['body'];
        $externalOrderId = $callbackBody['external_order_id'] ?? null;
        $statusKey = $callbackBody['order_status']['key'] ?? null;
        $bogOrderId = $callbackBody['order_id'] ?? null;

        if (!$externalOrderId) {
            return response()->json(['status' => 'error'], 400);
        }

        $paymentData = $this->payment::where(['id' => $externalOrderId])->first();
        if (!$paymentData) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($statusKey === 'completed') {
            $updatedPayment = $this->finalizeSuccessfulPayment($paymentData, $bogOrderId);
            if ($updatedPayment) {
                $this->callSuccessHook($updatedPayment);
            }
        } elseif ($this->isFailureStatus($statusKey)) {
            $failedPayment = $this->finalizeFailedPayment($paymentData, $bogOrderId, $statusKey);
            if ($failedPayment) {
                $this->callFailureHook($failedPayment);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function success(Request $request)
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->first();

        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($data->is_paid) {
            return $this->payment_response($data, 'success');
        }

        $bogOrderId = $data->transaction_id;
        if ($bogOrderId) {
            $accessToken = $this->getAccessToken();
            if ($accessToken) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->receipt_url . $bogOrderId);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                $order = json_decode($response, true);
                $statusKey = $order['order_status']['key'] ?? null;

                if ($statusKey === 'completed') {
                    $updatedPayment = $this->finalizeSuccessfulPayment($data, $order['order_id'] ?? $bogOrderId);
                    if ($updatedPayment) {
                        $this->callSuccessHook($updatedPayment);
                        $data = $updatedPayment;
                    } else {
                        $data = $this->payment::where(['id' => $data->id])->first() ?? $data;
                    }

                    return $this->payment_response($data, 'success');
                }

                if ($this->isFailureStatus($statusKey)) {
                    $failedPayment = $this->finalizeFailedPayment($data, $order['order_id'] ?? $bogOrderId, $statusKey);
                    if ($failedPayment) {
                        $this->callFailureHook($failedPayment);
                        $data = $failedPayment;
                    }
                }
            }
        }

        return $this->payment_response($data, 'fail');
    }

    public function fail(Request $request)
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->first();

        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($data?->is_paid) {
            return $this->payment_response($data, 'success');
        }

        $bogOrderId = $data?->transaction_id;
        if ($data && $bogOrderId) {
            $accessToken = $this->getAccessToken();
            if ($accessToken) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->receipt_url . $bogOrderId);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                $order = json_decode($response, true);
                $statusKey = $order['order_status']['key'] ?? null;

                if ($statusKey === 'completed') {
                    $updatedPayment = $this->finalizeSuccessfulPayment($data, $order['order_id'] ?? $bogOrderId);
                    if ($updatedPayment) {
                        $this->callSuccessHook($updatedPayment);
                        $data = $updatedPayment;
                    } else {
                        $data = $this->payment::where(['id' => $data->id])->first() ?? $data;
                    }

                    return $this->payment_response($data, 'success');
                }

                if ($this->isFailureStatus($statusKey)) {
                    $failedPayment = $this->finalizeFailedPayment($data, $order['order_id'] ?? $bogOrderId, $statusKey);
                    if ($failedPayment) {
                        $this->callFailureHook($failedPayment);
                        $data = $failedPayment;
                    }
                }
            }
        }

        return $this->payment_response($data, 'fail');
    }
}
