<?php

namespace Tests\Unit;

use Tests\TestCase;

final class LiveOtpEntropyTest extends TestCase
{
    public function test_live_mode_issues_six_digit_non_static_random_codes(): void
    {
        config()->set('envAppMode_conf', 'live');

        $values = [];
        for ($i = 0; $i < 8; $i++) {
            $code = generateOtpCode();
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', $code);
            $values[$code] = true;
        }

        // Eight independent CSPRNG outputs are not a shared static OTP.
        $this->assertGreaterThan(1, count($values));
    }

    public function test_demo_mode_keeps_its_explicit_demo_only_contract(): void
    {
        config()->set('envAppMode_conf', 'demo');
        $this->assertSame(STATIC_OTP_CODE, generateOtpCode());
    }
}
