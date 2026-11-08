<?php

namespace App\Services\DeliveryMan;

use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\PaymentRequest;
use App\Services\BaseService;
use App\Support\Payment\KeepzCashCollectionSettlement;
use App\Traits\Payment\PaymentRedirectLinkTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryManWalletService extends BaseService
{
    use PaymentRedirectLinkTrait;

    public function collectCashPaymentLink(DeliveryMan $deliveryMan, array $data = []): ?string
    {
        return $this->paymentRedirectLink($deliveryMan, [
            'success_hook' => 'collect_cash_success',
            'failure_hook' => 'collect_cash_fail',
            'payment_method' => $data['payment_gateway'] ?? null,
            'payment_platform' => 'app',
            'receiver_id' => '100',
            'receiver_name' => 'Admin',
            'payer_name' => $deliveryMan->f_name,
            'amount' => $data['amount'] ?? null,
            'callback' => $data['callback'] ?? null,
            'attribute' => $deliveryMan->is_ride == 1 ? 'rider_collect_cash_payments' : 'deliveryman_collect_cash_payments',
            'attribute_id' => $deliveryMan->id,
            'additional_data' => ($data['payment_gateway'] ?? null) === 'keepz'
                ? ['cash_collection_verification_version' => 1]
                : [],
        ]);
    }

    public function cashPaymentReference(DeliveryMan $deliveryMan, ?string $redirectLink): ?PaymentRequest
    {
        parse_str((string) parse_url((string) $redirectLink, PHP_URL_QUERY), $query);

        return $this->ownedCashPayment($deliveryMan, $query['payment_id'] ?? null);
    }

    public function ownedCashPayment(DeliveryMan $deliveryMan, mixed $paymentId): ?PaymentRequest
    {
        if (! is_string($paymentId) || ! \Illuminate\Support\Str::isUuid($paymentId)) {
            return null;
        }

        return PaymentRequest::query()
            ->whereKey($paymentId)
            ->where('payment_method', 'keepz')
            ->where('payer_id', $deliveryMan->id)
            ->where('attribute_id', $deliveryMan->id)
            ->where('attribute', $deliveryMan->is_ride == 1 ? 'rider_collect_cash_payments' : 'deliveryman_collect_cash_payments')
            ->where('success_hook', 'collect_cash_success')
            ->first();
    }

    /** Read only: payment callbacks, never a status poll, update the ledger. */
    public function cashPaymentStatus(PaymentRequest $payment): array
    {
        $paid = (bool) $payment->is_paid;
        $settled = $paid && KeepzCashCollectionSettlement::hasReceipt($payment);
        $metadata = json_decode($payment->additional_data ?? '[]', true);
        $failed = ! $paid && ! empty($metadata['keepz_failure_handled']);

        return [
            'payment_id' => (string) $payment->id,
            'status' => $settled ? 'success' : ($failed ? 'failed' : 'pending'),
            'is_paid' => $paid,
            'is_settled' => $settled,
        ];
    }

    public function adjust(mixed $deliveryManId): array
    {
        $wallet = $this->findOrNew($deliveryManId);
        $walletEarning = round($wallet->total_earning - ($wallet->total_withdrawn + $wallet->pending_withdraw), 8);

        if ($wallet->collected_cash == 0 || $walletEarning == 0) {
            return ['status_code' => 201, 'message' => translate('Already adjusted')];
        }

        $isPartial = $wallet->collected_cash - $walletEarning > 0;
        $amount = $isPartial ? $walletEarning : $wallet->collected_cash;

        $wallet->total_withdrawn = $wallet->total_withdrawn + $amount;
        $wallet->collected_cash = $isPartial ? $wallet->collected_cash - $walletEarning : 0;
        $wallet->save();

        app(ProvideDMEarningService::class)->recordAdjustment($deliveryManId, $amount, $isPartial);

        return ['status_code' => 200, 'message' => translate('Deliveryman wallet adjustment successful')];
    }

    public function getProvidedEarningList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return app(ProvideDMEarningService::class)->getAdjustmentList($filters, $paginate);
    }

    public function findOrNew(mixed $deliveryManId): DeliveryManWallet
    {
        return DeliveryManWallet::firstOrNew(['delivery_man_id' => $deliveryManId]);
    }
}
