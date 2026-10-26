<?php

namespace App\Services\Order;

/**
 * Short handover code sent to the customer/courier for an order.
 * Preserve the four-digit integer wire/database contract, but use
 * a cryptographically secure generator instead of PHP's rand().
 */
final class OrderOtpGenerator
{
    public function generate(): int
    {
        return random_int(1000, 9999);
    }
}
