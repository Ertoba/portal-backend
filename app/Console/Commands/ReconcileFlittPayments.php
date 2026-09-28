<?php

namespace App\Console\Commands;

use App\Http\Controllers\FlittPaymentController;
use App\Models\PaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileFlittPayments extends Command
{
    protected $signature = 'payments:reconcile-flitt {--limit=50 : Maximum pending payment requests to check}';

    protected $description = 'Reconcile pending Flitt payment requests against Flitt order status API';

    public function handle(FlittPaymentController $flittPaymentController): int
    {
        $limit = max(1, min((int) $this->option('limit'), 200));

        $payments = PaymentRequest::query()
            ->where('payment_method', 'flitt')
            ->where('is_paid', 0)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $checked = 0;

        foreach ($payments as $payment) {
            $checked++;

            try {
                $status = $flittPaymentController->reconcilePaymentRequest($payment);
                $this->line("{$payment->id}: " . ($status ?: 'no_status'));
            } catch (\Throwable $exception) {
                Log::error('Flitt reconciliation failed', [
                    'payment_request_id' => $payment->id,
                    'exception' => $exception->getMessage(),
                ]);

                $this->error("{$payment->id}: failed");
            }
        }

        $this->info("Checked {$checked} Flitt payment request(s).");

        return self::SUCCESS;
    }
}
