<?php

namespace App\Support\Payment;

use App\Models\AccountTransaction;
use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\PaymentRequest;
use App\Mail\CollectCashMail;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * A receipt belongs to one newly created Keepz cash-collection payment, not
 * merely to a courier's balance or a browser redirect. Existing payments use
 * the legacy hook and are never retrospectively settled by this code.
 */
final class KeepzCashCollectionSettlement
{
    public static function handles(PaymentRequest $payment): bool
    {
        $metadata = json_decode($payment->additional_data ?? '[]', true);

        return $payment->payment_method === 'keepz'
            && in_array($payment->attribute, ['deliveryman_collect_cash_payments', 'rider_collect_cash_payments'], true)
            && ($metadata['cash_collection_verification_version'] ?? null) === 1;
    }

    public static function hasReceipt(PaymentRequest $payment): bool
    {
        if (! self::handles($payment)) {
            return false;
        }

        $metadata = json_decode($payment->additional_data ?? '[]', true);
        $receipt = $metadata['cash_collection_receipt'] ?? null;
        if (! is_array($receipt) || ($receipt['payment_id'] ?? null) !== (string) $payment->id) {
            return false;
        }

        return AccountTransaction::query()
            ->whereKey($receipt['account_transaction_id'] ?? null)
            ->where('from_id', $payment->attribute_id)
            ->where('from_type', $payment->attribute === 'rider_collect_cash_payments' ? 'rider' : 'deliveryman')
            ->where('method', 'keepz')
            ->where('ref', $payment->attribute)
            ->where('amount', $payment->payment_amount)
            ->exists();
    }

    /** Must run inside the same transaction that marks the payment paid. */
    public static function settle(PaymentRequest $payment): bool
    {
        return DB::transaction(static function () use ($payment): bool {
            $locked = PaymentRequest::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (! self::handles($locked) || ! (bool) $locked->is_paid
                || (string) $locked->payer_id !== (string) $locked->attribute_id) {
                throw new RuntimeException('Cash collection payment is not verified.');
            }

            if (self::hasReceipt($locked)) {
                return false;
            }

            $metadata = json_decode($locked->additional_data ?? '[]', true);
            // A recorded but missing/mismatched ledger row is an inconsistency,
            // not permission to debit the courier a second time.
            if (! empty($metadata['cash_collection_receipt'])) {
                throw new RuntimeException('Cash collection receipt is inconsistent.');
            }

            $courier = DeliveryMan::withoutGlobalScope('delivery_only')->findOrFail($locked->attribute_id);
            $wallet = DeliveryManWallet::query()->where('delivery_man_id', $courier->id)->lockForUpdate()->firstOrFail();
            $adminId = Admin::query()->where('role_id', 1)->value('id');
            $adminWallet = AdminWallet::query()->where('admin_id', $adminId)->lockForUpdate()->firstOrFail();
            $amount = (float) $locked->payment_amount;
            if (! is_finite($amount) || $amount <= 0) {
                throw new RuntimeException('Cash collection amount is invalid.');
            }

            $transaction = new AccountTransaction();
            $transaction->from_type = $locked->attribute === 'rider_collect_cash_payments' ? 'rider' : 'deliveryman';
            $transaction->from_id = $courier->id;
            $transaction->created_by = $transaction->from_type;
            $transaction->method = 'keepz';
            $transaction->ref = $locked->attribute;
            $transaction->amount = $locked->payment_amount;
            $transaction->current_balance = $wallet->collected_cash;
            $transaction->saveOrFail();

            $wallet->decrement('collected_cash', $amount);
            $adminWallet->increment('digital_received', $amount);
            $courier->status = 1;
            $courier->saveOrFail();

            $metadata['cash_collection_receipt'] = [
                'payment_id' => (string) $locked->id,
                'account_transaction_id' => $transaction->id,
                'settled_at' => now()->toIso8601String(),
            ];
            $locked->additional_data = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $locked->saveOrFail();

            // Preserve the existing optional receipt email, but never let a
            // mail error roll back money or send mail before the ledger commits.
            DB::afterCommit(static function () use ($locked, $courier, $transaction): void {
                try {
                    if ($locked->attribute === 'deliveryman_collect_cash_payments'
                        && SendNotification::canSendMail('cash_collect_mail_status_dm', 'deliveryman', 'deliveryman_collect_cash')) {
                        SendNotification::mail($courier->getRawOriginal('email'), new CollectCashMail($transaction, $courier));
                    }
                } catch (\Throwable $exception) {
                    Log::warning('Cash collection receipt email could not be sent', ['payment_id' => $locked->id]);
                }
            });

            return true;
        }, 3);
    }
}
