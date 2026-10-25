<?php

namespace Tests\Unit;

use App\Support\Payment\KeepzStatusIdentity;
use PHPUnit\Framework\TestCase;

final class KeepzStatusIdentityTest extends TestCase
{
    private const PAYMENT_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    public function test_matching_provider_order_is_accepted(): void
    {
        $this->assertTrue(KeepzStatusIdentity::matches(self::PAYMENT_ID, [
            'integratorOrderId' => self::PAYMENT_ID,
            'status' => 'SUCCESS',
        ]));
        $this->assertTrue(KeepzStatusIdentity::matches(self::PAYMENT_ID, [
            'integratorOrderId' => strtoupper(self::PAYMENT_ID),
            'status' => 'PROCESSING',
        ]));
    }

    public function test_unrelated_or_missing_provider_order_is_rejected(): void
    {
        foreach ([
            ['integratorOrderId' => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', 'status' => 'SUCCESS'],
            ['status' => 'SUCCESS'],
            ['integratorOrderId' => null, 'status' => 'SUCCESS'],
            ['integratorOrderId' => '', 'status' => 'SUCCESS'],
            ['integratorOrderId' => 123, 'status' => 'SUCCESS'],
        ] as $statusPayload) {
            $this->assertFalse(KeepzStatusIdentity::matches(self::PAYMENT_ID, $statusPayload));
        }
    }
}
