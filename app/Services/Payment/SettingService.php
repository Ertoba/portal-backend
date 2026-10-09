<?php

namespace App\Services\Payment;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;
use App\Services\BaseService;

class SettingService extends BaseService
{
    // In-house gateways must remain discoverable even when the optional
    // payment add-on publish flag is off. These are not license-dependent.
    public const BUILT_IN_PAYMENT_GATEWAYS = [
        'ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay',
        'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave',
        'liqpay', 'bkash', 'mercadopago', 'bog_pay', 'flitt', 'keepz',
    ];

    /** Check the active credential set; never expose an unconfigured gateway. */
    public static function isGatewayEnabled(object $method, string $environment): bool
    {
        if ((int) ($method->is_active ?? 0) !== 1 || ($method->settings_type ?? '') !== 'payment_config') {
            return false;
        }

        $field = $environment === 'live' ? 'live_values' : 'test_values';
        $credentials = json_decode($method->{$field} ?? 'null', true);
        if (!is_array($credentials) || !in_array((string) ($credentials['status'] ?? '0'), ['1', 'true', 'on'], true)) {
            return false;
        }

        // Keep checkout in the same mode as the payment controller. In a live
        // deployment, never offer a gateway that would run in test mode.
        return $environment !== 'live' || !isset($method->mode) || $method->mode === 'live';
    }

    public function hasActiveSmsGateway(): bool
    {
        return Setting::whereJsonContains('live_values->status', '1')
            ->where('settings_type', 'sms_config')
            ->exists();
    }

    public function smsGatewayConfigs(): array
    {
        return Setting::where('settings_type', 'sms_config')
            ->pluck('live_values', 'key_name')
            ->all();
    }

    public function getActiveGateways(): array
    {
        if (! Schema::hasTable('addon_settings')) {
            return [];
        }

        $digitalPayment = Helpers::get_business_settings('digital_payment');
        if ($digitalPayment && (int) ($digitalPayment['status'] ?? 0) !== 1) {
            return [];
        }

        $publishConfig = config('get_payment_publish_status');
        $published = (int) ($publishConfig[0]['is_published'] ?? 0) === 1;
        $environment = env('APP_ENV') === 'live' ? 'live' : 'test';

        $query = DB::table('addon_settings')
            ->where('is_active', 1)
            ->where('settings_type', 'payment_config');

        if (!$published) {
            $query->whereIn('key_name', self::BUILT_IN_PAYMENT_GATEWAYS);
        }

        $data = [];
        foreach ($query->get() as $method) {
            if (!self::isGatewayEnabled($method, $environment)) {
                continue;
            }

            $additional = json_decode($method->additional_data ?? 'null');
            $data[] = [
                'gateway' => $method->key_name,
                'gateway_title' => $additional?->gateway_title ?: strtoupper($method->key_name),
                'gateway_image' => $additional?->gateway_image,
                'gateway_image_full_url' => Helpers::get_full_url(
                    'payment_modules/gateway_image',
                    $additional?->gateway_image,
                    $additional?->storage ?? 'public'
                ),
            ];
        }

        return $data;

    }
}
