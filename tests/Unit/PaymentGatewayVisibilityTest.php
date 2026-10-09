<?php

namespace Tests\Unit;

use App\Services\Payment\SettingService;
use PHPUnit\Framework\TestCase;

class PaymentGatewayVisibilityTest extends TestCase
{
    private function gateway(array $overrides = []): object
    {
        return (object) array_merge([
            'key_name' => 'keepz',
            'settings_type' => 'payment_config',
            'is_active' => 1,
            'mode' => 'live',
            'live_values' => json_encode(['status' => '1']),
            'test_values' => json_encode(['status' => '0']),
        ], $overrides);
    }

    public function test_in_house_gateways_remain_discoverable_without_addon_publish_flag(): void
    {
        foreach (['keepz', 'bog_pay', 'flitt'] as $gateway) {
            $this->assertContains($gateway, SettingService::BUILT_IN_PAYMENT_GATEWAYS);
        }
    }

    public function test_active_live_gateway_is_visible(): void
    {
        $this->assertTrue(SettingService::isGatewayEnabled($this->gateway(), 'live'));
    }

    public function test_disabled_gateway_is_not_visible_even_with_enabled_credentials(): void
    {
        $this->assertFalse(SettingService::isGatewayEnabled($this->gateway(['is_active' => 0]), 'live'));
    }

    public function test_live_site_never_displays_test_mode_gateway(): void
    {
        $this->assertFalse(SettingService::isGatewayEnabled($this->gateway(['mode' => 'test']), 'live'));
    }

    public function test_credentials_in_other_environment_cannot_enable_live_gateway(): void
    {
        $this->assertFalse(SettingService::isGatewayEnabled($this->gateway([
            'live_values' => json_encode(['status' => '0']),
            'test_values' => json_encode(['status' => '1']),
        ]), 'live'));
    }

    public function test_test_environment_uses_test_status(): void
    {
        $this->assertTrue(SettingService::isGatewayEnabled($this->gateway([
            'mode' => 'test',
            'test_values' => json_encode(['status' => 1]),
            'live_values' => json_encode(['status' => 0]),
        ]), 'test'));
    }

    public function test_null_and_corrupt_credentials_are_rejected(): void
    {
        $this->assertFalse(SettingService::isGatewayEnabled($this->gateway(['live_values' => null]), 'live'));
        $this->assertFalse(SettingService::isGatewayEnabled($this->gateway(['live_values' => 'invalid']), 'live'));
        $this->assertFalse(SettingService::isGatewayEnabled($this->gateway(['live_values' => '{}']), 'live'));
    }
}
