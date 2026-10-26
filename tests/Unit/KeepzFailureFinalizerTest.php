<?php

namespace Tests\Unit;

use App\Models\PaymentRequest;
use App\Support\Payment\KeepzFailureFinalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KeepzFailureFinalizerTest extends TestCase
{
    private const PAYMENT_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('is_paid')->default(false);
            $table->string('transaction_id')->nullable();
            $table->text('additional_data')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('payment_requests');

        parent::tearDown();
    }

    private function payment(bool $paid = false): PaymentRequest
    {
        DB::table('payment_requests')->insert([
            'id' => self::PAYMENT_ID,
            'is_paid' => $paid,
            'transaction_id' => null,
            'additional_data' => json_encode(['keepz_order_url_received' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PaymentRequest::query()->findOrFail(self::PAYMENT_ID);
    }

    public function test_only_first_terminal_failure_claim_runs_the_failure_hook(): void
    {
        $payment = $this->payment();

        $first = KeepzFailureFinalizer::claim($payment, 'provider-123', 'failed');
        $this->assertNotNull($first);

        $metadata = json_decode($first->additional_data, true);
        $this->assertTrue($metadata['keepz_order_url_received']);
        $this->assertSame('failed', $metadata['keepz_failure_status']);
        $this->assertNotEmpty($metadata['keepz_failure_handled']);
        $this->assertSame('provider-123', $first->transaction_id);

        // Simulate a stale PaymentRequest held by a second callback. It must
        // re-read the row inside the lock, not trust its old in-memory state.
        $this->assertNull(KeepzFailureFinalizer::claim($payment, 'provider-456', 'cancelled'));
        $reloaded = PaymentRequest::query()->findOrFail(self::PAYMENT_ID);
        $this->assertSame('provider-123', $reloaded->transaction_id);
        $this->assertSame($first->additional_data, $reloaded->additional_data);
    }

    public function test_paid_payment_can_never_be_claimed_as_failure(): void
    {
        $payment = $this->payment(true);
        $this->assertNull(KeepzFailureFinalizer::claim($payment, 'provider-123', 'failed'));

        $reloaded = PaymentRequest::query()->findOrFail(self::PAYMENT_ID);
        $this->assertTrue((bool) $reloaded->is_paid);
        $this->assertSame(['keepz_order_url_received' => true], json_decode($reloaded->additional_data, true));
    }

    public function test_missing_payment_returns_no_failure_claim(): void
    {
        $payment = new PaymentRequest();
        $payment->id = self::PAYMENT_ID;
        $this->assertNull(KeepzFailureFinalizer::claim($payment, null, 'failed'));
    }
}
