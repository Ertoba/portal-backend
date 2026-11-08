<?php

namespace Tests\Feature;

use App\Services\CheckoutPaymentRecoveryService;
use App\Services\KeepzGatewayLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/** Contract regression tests: no live gateway or financial mutation. */
final class CheckoutPaymentRecoveryStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Http::preventStrayRequests();
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_guest')->default(true);
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->default('digital_payment');
            $table->string('order_status')->default('canceled');
            $table->timestamps();
        });
        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('attribute')->default('order');
            $table->unsignedBigInteger('attribute_id')->default(1001);
            $table->string('payment_method')->default('keepz');
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });
        DB::table('orders')->insert(['id' => 1001, 'user_id' => 11]);
        DB::table('payment_requests')->insert(['id' => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function request(int $guestId = 11): Request
    {
        return Request::create('/api/v1/customer/order-payment/1001/status', 'GET', ['guest_id' => $guestId]);
    }

    private function service(?array $inspection = null): CheckoutPaymentRecoveryService
    {
        $gateway = Mockery::mock(KeepzGatewayLifecycleService::class);
        if ($inspection !== null) {
            $gateway->shouldReceive('inspect')->once()->andReturn($inspection);
        } else {
            $gateway->shouldNotReceive('inspect');
        }
        $gateway->shouldNotReceive('cancel');

        return new CheckoutPaymentRecoveryService($gateway);
    }

    public function test_canceled_order_with_active_provider_attempt_is_not_safe_to_release(): void
    {
        $response = $this->service(['state' => 'active', 'provider_status' => 'processing'])
            ->status($this->request(), 1001);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('active', $response->getData(true)['state']);
        $this->assertSame('canceled', $response->getData(true)['order_status']);
        $this->assertSame('unpaid', $response->getData(true)['payment_status']);
        $this->assertSame(1001, $response->getData(true)['order_id']);
        Http::assertNothingSent();
    }

    public function test_canceled_order_with_terminal_attempt_returns_ready_without_canceling_again(): void
    {
        $response = $this->service(['state' => 'terminal', 'provider_status' => 'canceled'])
            ->status($this->request(), 1001);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ready', $response->getData(true)['state']);
        $this->assertSame('canceled', $response->getData(true)['order_status']);
        $this->assertSame('unpaid', $response->getData(true)['payment_status']);
        $this->assertSame(1, DB::table('payment_requests')->count());
        $this->assertSame('canceled', DB::table('orders')->value('order_status'));
    }

    public function test_unknown_provider_status_retains_order_identity_and_returns_conflict(): void
    {
        $response = $this->service(['state' => 'unknown', 'provider_status' => null, 'error' => 'status_unavailable'])
            ->status($this->request(), 1001);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('unknown', $response->getData(true)['state']);
        $this->assertSame(1001, $response->getData(true)['order_id']);
        $this->assertSame('canceled', $response->getData(true)['order_status']);
        $this->assertSame('unpaid', $response->getData(true)['payment_status']);
    }

    public function test_paid_order_wins_without_another_provider_call(): void
    {
        DB::table('orders')->where('id', 1001)->update(['payment_status' => 'paid']);
        $response = $this->service()->status($this->request(), 1001);
        $this->assertSame('paid', $response->getData(true)['state']);
        $this->assertSame('paid', $response->getData(true)['payment_status']);
    }

    public function test_foreign_order_never_queries_provider_or_discloses_payment_state(): void
    {
        $response = $this->service()->status($this->request(12), 1001);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayNotHasKey('order_id', $response->getData(true));
    }

    public function test_canceled_order_cannot_prepare_a_new_payment_attempt(): void
    {
        $response = $this->service()->prepareRetry($this->request(), 1001);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('canceled', $response->getData(true)['order_status']);
        $this->assertSame(1, DB::table('payment_requests')->count());
        Http::assertNothingSent();
    }

    public function test_changed_method_is_superseded_only_after_old_provider_attempt_is_terminal(): void
    {
        DB::table('orders')->where('id', 1001)->update(['payment_method' => 'cash_on_delivery']);
        $response = $this->service(['state' => 'terminal', 'provider_status' => 'expired'])
            ->status($this->request(), 1001);
        $this->assertSame('superseded', $response->getData(true)['state']);
        $this->assertSame('cash_on_delivery', $response->getData(true)['payment_method']);
    }
}
