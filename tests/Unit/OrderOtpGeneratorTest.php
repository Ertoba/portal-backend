<?php

namespace Tests\Unit;

use App\Services\Order\OrderOtpGenerator;
use Tests\TestCase;

final class OrderOtpGeneratorTest extends TestCase
{
    public function test_handover_code_is_always_four_digits(): void
    {
        $generator = app(OrderOtpGenerator::class);

        for ($i = 0; $i < 100; $i++) {
            $otp = $generator->generate();
            $this->assertIsInt($otp);
            $this->assertGreaterThanOrEqual(1000, $otp);
            $this->assertLessThanOrEqual(9999, $otp);
        }
    }

    public function test_failed_order_exception_is_not_serialized_to_api_clients(): void
    {
        $trait = file_get_contents(app_path('Traits/Order/PlaceNewOrderTrait.php'));
        $this->assertStringNotContainsString('response()->json([$exception]', $trait);
        $this->assertStringContainsString("'code' => 'order'", $trait);
        $this->assertStringContainsString("translate('messages.Failed to place order')", $trait);
    }

    public function test_new_orders_use_the_secure_handover_code_generator(): void
    {
        $trait = file_get_contents(app_path('Traits/Order/PlaceNewOrderTrait.php'));
        $this->assertStringContainsString('OrderOtpGenerator::class)->generate()', $trait);
        $this->assertStringNotContainsString('->otp = rand(', $trait);
    }
}
