<?php

namespace App\Support\Payment;

/**
 * A redirect, network error or order-creation error is not proof that an
 * acquiring attempt has failed. Only an explicitly terminal status obtained
 * from the provider can trigger the order_failed hook.
 *
 * This helper deliberately does NOT verify order identity: that check happens
 * separately on the authenticated GET status response before processKeepzStatus.
 */
final class KeepzVerifiedFailure
{
    public static function matches(?array $response): bool
    {
        if ($response === null || isset($response['statusCode'])) {
            return false;
        }

        $status = $response['status'] ?? $response['orderStatus'] ?? null;
        if (! is_string($status)) {
            return false;
        }

        $status = strtolower(str_replace([' ', '-'], '_', trim($status)));

        return in_array($status, ['failed', 'failure', 'canceled', 'cancelled', 'expired'], true);
    }
}
