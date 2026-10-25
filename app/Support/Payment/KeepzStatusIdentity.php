<?php

namespace App\Support\Payment;

/**
 * Keepz status responses must belong to the PaymentRequest we queried.
 * The provider documents integratorOrderId in successful GET status payloads.
 */
final class KeepzStatusIdentity
{
    public static function matches(string $paymentRequestId, array $statusPayload): bool
    {
        $providerOrderId = $statusPayload['integratorOrderId'] ?? null;

        return is_string($providerOrderId)
            && $providerOrderId !== ''
            && hash_equals(strtolower($paymentRequestId), strtolower($providerOrderId));
    }
}
