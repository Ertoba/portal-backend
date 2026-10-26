<?php

namespace App\Support\Payment;

use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;

/**
 * Atomically claim the failure hook for a provider-confirmed terminal status.
 *
 * Two callbacks can race after both read an unpaid payment. The row lock makes
 * reading/updating the failure marker a single transaction, so only one can
 * claim the failure hook. An already paid or already handled payment is never
 * claimed again. This does not replace provider-status verification.
 */
final class KeepzFailureFinalizer
{
    public static function claim(PaymentRequest $payment, ?string $transactionId, ?string $status): ?PaymentRequest
    {
        return DB::transaction(static function () use ($payment, $transactionId, $status): ?PaymentRequest {
            $locked = PaymentRequest::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || (bool) $locked->is_paid) {
                return null;
            }

            $metadata = json_decode($locked->additional_data ?? '[]', true);
            $metadata = is_array($metadata) ? $metadata : [];

            if (! empty($metadata['keepz_failure_handled'])) {
                return null;
            }

            $metadata['keepz_failure_handled'] = now()->toIso8601String();
            $metadata['keepz_failure_status'] = $status;
            $locked->additional_data = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($transactionId !== null && $transactionId !== '' && empty($locked->transaction_id)) {
                $locked->transaction_id = $transactionId;
            }

            $locked->save();

            return $locked;
        }, 3);
    }
}
