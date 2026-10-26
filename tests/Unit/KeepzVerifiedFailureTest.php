<?php

namespace Tests\Unit;

use App\Support\Payment\KeepzVerifiedFailure;
use PHPUnit\Framework\TestCase;

final class KeepzVerifiedFailureTest extends TestCase
{
    public function test_provider_confirmed_terminal_failures_can_be_finalized(): void
    {
        foreach (['failed', 'failure', 'CANCELED', 'cancelled', ' EXPIRED ', 'orderStatus'] as $status) {
            if ($status === 'orderStatus') {
                $this->assertTrue(KeepzVerifiedFailure::matches(['orderStatus' => 'FAILED']));
            } else {
                $this->assertTrue(KeepzVerifiedFailure::matches(['status' => $status]));
            }
        }
    }

    public function test_unknown_or_unverified_create_results_must_stay_pending(): void
    {
        foreach ([
            null,
            [],
            ['statusCode' => 6005],
            ['statusCode' => 503, 'status' => 'FAILED'],
            ['status' => 'PROCESSING'],
            ['status' => 'SUCCESS'],
            ['status' => 'INITIAL'],
            ['status' => 'REFUNDED'],
            ['status' => 0],
            ['message' => 'network_timeout'],
        ] as $response) {
            $this->assertFalse(KeepzVerifiedFailure::matches($response));
        }
    }
}
