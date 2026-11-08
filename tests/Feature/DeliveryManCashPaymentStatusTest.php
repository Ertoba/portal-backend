<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\DeliveryMan\Wallet\WalletController;
use App\Http\Controllers\KeepzPaymentController;
use App\Http\Middleware\DmTokenIsValid;
use App\Models\DeliveryMan;
use App\Models\PaymentRequest;
use App\Services\DeliveryMan\DeliveryManWalletService;
use App\Support\Payment\KeepzCashCollectionSettlement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionClass;
use Tests\TestCase;
use Throwable;

final class DeliveryManCashPaymentStatusTest extends TestCase
{
    private const PAYMENT_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const STATUS_PATH = '/api/testing/collected-cash-payment-status';

    protected function setUp(): void
    {
        parent::setUp();
        // This suite owns a disposable, in-memory database only. It must not
        // depend on a developer's configured database or make provider calls.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Http::preventStrayRequests();

        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('is_paid')->default(false);
            $table->string('payment_method')->default('keepz');
            $table->string('payer_id');
            $table->string('attribute_id');
            $table->string('attribute');
            $table->string('success_hook')->default('collect_cash_success');
            $table->string('failure_hook')->default('collect_cash_fail');
            $table->decimal('payment_amount', 24, 8)->default(10);
            $table->string('currency_code')->default('GEL');
            $table->string('transaction_id')->nullable();
            $table->text('additional_data')->nullable();
            $table->timestamps();
        });
        Schema::create('delivery_men', function (Blueprint $table): void {
            $table->id();
            $table->string('auth_token');
            $table->boolean('is_ride')->default(false);
            $table->boolean('is_delivery')->default(true);
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
        Schema::create('delivery_man_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('delivery_man_id')->unique();
            $table->decimal('collected_cash', 24, 8);
            $table->timestamps();
        });
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
        });
        Schema::create('admin_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->decimal('digital_received', 24, 8)->default(0);
            $table->timestamps();
        });
        Schema::create('account_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('from_type');
            $table->unsignedBigInteger('from_id');
            $table->string('created_by');
            $table->string('method');
            $table->string('ref');
            $table->decimal('amount', 24, 8);
            $table->decimal('current_balance', 24, 8);
            $table->timestamps();
        });
        DB::table('delivery_men')->insert([
            ['id' => 1, 'auth_token' => 'owner-token', 'is_ride' => false],
            ['id' => 2, 'auth_token' => 'other-token', 'is_ride' => false],
        ]);
        DB::table('delivery_man_wallets')->insert(['delivery_man_id' => 1, 'collected_cash' => 50]);
        DB::table('admins')->insert(['id' => 100, 'role_id' => 1]);
        DB::table('admin_wallets')->insert(['admin_id' => 100, 'digital_received' => 0]);

        // Exercise the real controller and courier authentication middleware
        // without unrelated global feature/config fixtures.
        Route::get(self::STATUS_PATH, [WalletController::class, 'cashPaymentStatus'])->middleware(DmTokenIsValid::class);
        Route::post('/api/testing/make-collected-cash-payment', [WalletController::class, 'collectCashPayment'])->middleware(DmTokenIsValid::class);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function payment(array $overrides = []): PaymentRequest
    {
        DB::table('payment_requests')->insert(array_merge([
            'id' => self::PAYMENT_ID,
            'payer_id' => '1',
            'attribute_id' => '1',
            'attribute' => 'deliveryman_collect_cash_payments',
            'additional_data' => json_encode(['cash_collection_verification_version' => 1]),
        ], $overrides));

        return PaymentRequest::query()->findOrFail(self::PAYMENT_ID);
    }

    private function statusUrl(?string $token = 'owner-token', string $paymentId = self::PAYMENT_ID): string
    {
        return self::STATUS_PATH.'?'.http_build_query(array_filter(['token' => $token, 'payment_id' => $paymentId]));
    }

    private function verifiedSuccess(PaymentRequest $payment): void
    {
        // All public gateway entry points verify encrypted provider identity
        // before this internal finalizer. No network or real payment is made.
        $reflection = new ReflectionClass(KeepzPaymentController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('payment')->setValue($controller, new PaymentRequest());
        $reflection->getMethod('processKeepzStatus')->invoke($controller, $payment, [
            'integratorOrderId' => self::PAYMENT_ID,
            'status' => 'SUCCESS',
            'transactionId' => 'provider-receipt-123',
        ]);
    }

    public function test_actual_status_route_is_courier_authenticated(): void
    {
        $route = Route::getRoutes()->getByName('deliveryman_cash_payment_status');
        $this->assertNotNull($route);
        $this->assertContains('dm.api', $route->gatherMiddleware());
        $this->assertContains('GET', $route->methods());
    }

    public function test_status_requires_valid_courier_authentication(): void
    {
        $this->payment();
        $this->getJson($this->statusUrl(null))->assertUnauthorized();
        $this->getJson($this->statusUrl('invalid-token'))->assertUnauthorized();
    }

    public function test_capability_probe_is_authenticated_read_only_and_does_not_create_a_payment(): void
    {
        $this->getJson(self::STATUS_PATH)->assertUnauthorized();
        $this->getJson(self::STATUS_PATH.'?token=owner-token')->assertOk()
            ->assertJsonPath('content.verification_version', 1)
            ->assertJsonPath('content.supports_embedded_keepz', true)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(0, DB::table('payment_requests')->count());
        $this->assertSame(0, DB::table('account_transactions')->count());
        Http::assertNothingSent();
    }

    public function test_cross_courier_cannot_read_another_payment(): void
    {
        $this->payment();
        $this->getJson($this->statusUrl('other-token'))->assertNotFound();
    }

    public function test_payment_owner_and_collection_attribute_must_both_match(): void
    {
        foreach ([
            ['payer_id' => '2'],
            ['attribute_id' => '2'],
            ['attribute' => 'order'],
            ['attribute' => 'rider_collect_cash_payments'],
            ['success_hook' => 'order_place'],
            ['payment_method' => 'stripe'],
        ] as $invalid) {
            DB::table('payment_requests')->delete();
            $this->payment($invalid);
            $this->getJson($this->statusUrl())->assertNotFound();
        }
    }

    public function test_missing_and_invalid_payment_ids_have_no_payment_information(): void
    {
        $this->payment();
        $this->getJson($this->statusUrl('owner-token', 'not-a-uuid'))->assertNotFound();
        $this->getJson(self::STATUS_PATH.'?token=owner-token&payment_id=')->assertNotFound();
        $this->getJson($this->statusUrl('owner-token', 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb'))->assertNotFound();
    }

    public function test_pending_status_is_read_only_and_does_not_treat_redirect_flags_as_payment(): void
    {
        $this->payment();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->getJson($this->statusUrl().'&status=success&flag=success&is_paid=1')
                ->assertOk()->assertJsonPath('content.status', 'pending')
                ->assertJsonPath('content.is_paid', false)->assertJsonPath('content.is_settled', false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame(1, DB::table('payment_requests')->count());
        $this->assertSame(0, DB::table('account_transactions')->count());
        $this->assertEquals(50, DB::table('delivery_man_wallets')->value('collected_cash'));
        Http::assertNothingSent();
    }

    public function test_paid_flag_without_payment_specific_receipt_is_still_pending(): void
    {
        $this->payment(['is_paid' => true]);
        $this->getJson($this->statusUrl())->assertOk()
            ->assertJsonPath('content.status', 'pending')->assertJsonPath('content.is_paid', true)
            ->assertJsonPath('content.is_settled', false);
        $this->assertSame(0, DB::table('account_transactions')->count());
    }

    public function test_only_provider_verified_terminal_failure_is_reported_failed(): void
    {
        $this->payment(['additional_data' => json_encode([
            'cash_collection_verification_version' => 1,
            'keepz_failure_handled' => now()->toIso8601String(),
            'keepz_failure_status' => 'failed',
        ])]);
        $this->getJson($this->statusUrl())->assertOk()
            ->assertJsonPath('content.status', 'failed')->assertJsonPath('content.is_paid', false)
            ->assertJsonPath('content.is_settled', false);
    }

    public function test_duplicate_verified_callbacks_settle_exactly_once_and_return_receipt(): void
    {
        $payment = $this->payment();
        $this->verifiedSuccess($payment);
        // A stale second callback must reread the locked payment.
        $this->verifiedSuccess($payment);

        $this->assertSame(1, DB::table('account_transactions')->count());
        $this->assertEquals(40, DB::table('delivery_man_wallets')->value('collected_cash'));
        $this->assertEquals(10, DB::table('admin_wallets')->value('digital_received'));
        $this->getJson($this->statusUrl())->assertOk()
            ->assertJsonPath('content.payment_id', self::PAYMENT_ID)
            ->assertJsonPath('content.status', 'success')->assertJsonPath('content.is_paid', true)
            ->assertJsonPath('content.is_settled', true);
        Http::assertNothingSent();
    }

    public function test_missing_wallet_aborts_paid_flag_and_ledger_changes(): void
    {
        $payment = $this->payment();
        DB::table('admin_wallets')->delete();
        try {
            $this->verifiedSuccess($payment);
            $this->fail('Expected the missing settlement wallet to abort the callback.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\ModelNotFoundException::class, $exception);
        }
        $this->assertFalse((bool) $payment->fresh()->is_paid);
        $this->assertSame(0, DB::table('account_transactions')->count());
        $this->assertEquals(50, DB::table('delivery_man_wallets')->value('collected_cash'));
    }

    public function test_receipt_write_failure_rolls_back_paid_flag_and_both_balances(): void
    {
        $payment = $this->payment();
        DB::unprepared("CREATE TRIGGER reject_receipt BEFORE UPDATE ON payment_requests WHEN NEW.additional_data LIKE '%cash_collection_receipt%' BEGIN SELECT RAISE(ABORT, 'receipt write failed'); END");
        try {
            $this->verifiedSuccess($payment);
            $this->fail('Expected a rejected receipt write to abort the callback.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(\Illuminate\Database\QueryException::class, $exception);
        }
        $this->assertFalse((bool) $payment->fresh()->is_paid);
        $this->assertSame(0, DB::table('account_transactions')->count());
        $this->assertEquals(50, DB::table('delivery_man_wallets')->value('collected_cash'));
        $this->assertEquals(0, DB::table('admin_wallets')->value('digital_received'));
    }

    public function test_unpaid_or_unmarked_legacy_payment_cannot_be_retroactively_settled(): void
    {
        $payment = $this->payment();
        try {
            KeepzCashCollectionSettlement::settle($payment);
            $this->fail('An unpaid payment cannot settle.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cash collection payment is not verified.', $exception->getMessage());
        }
        DB::table('payment_requests')->where('id', self::PAYMENT_ID)->update(['is_paid' => true, 'additional_data' => '{}']);
        $this->getJson($this->statusUrl())->assertOk()->assertJsonPath('content.is_settled', false);
        $this->assertSame(0, DB::table('account_transactions')->count());
    }

    public function test_reference_comes_from_generated_link_and_remains_owner_scoped(): void
    {
        $this->payment();
        $service = app(DeliveryManWalletService::class);
        $owner = DeliveryMan::query()->findOrFail(1);
        $other = DeliveryMan::query()->findOrFail(2);
        $url = 'https://mili.ge/payment/keepz/pay/?payment_id='.self::PAYMENT_ID;
        $this->assertSame(self::PAYMENT_ID, $service->cashPaymentReference($owner, $url)?->id);
        $this->assertNull($service->cashPaymentReference($other, $url));
        $this->assertNull($service->cashPaymentReference($owner, 'https://mili.ge/no-id'));
    }

    public function test_creation_response_has_owned_payment_id_and_token_free_status_url(): void
    {
        $this->payment();
        $link = 'https://mili.ge/payment/keepz/pay/?payment_id='.self::PAYMENT_ID;
        $service = Mockery::mock(DeliveryManWalletService::class)->makePartial();
        $service->shouldReceive('collectCashPaymentLink')->once()->andReturn($link);
        $this->app->instance(DeliveryManWalletService::class, $service);

        $response = $this->postJson('/api/testing/make-collected-cash-payment', [
            'token' => 'owner-token',
            'payment_gateway' => 'keepz',
            'amount' => 10,
            'callback' => 'https://mili.ge/success',
        ])->assertOk()->assertJsonPath('content.payment_id', self::PAYMENT_ID)
            ->assertJsonPath('content.redirect_link', $link);

        $statusUrl = $response->json('content.status_url');
        $this->assertSame('/api/v1/delivery-man/collected-cash-payment-status', parse_url($statusUrl, PHP_URL_PATH));
        parse_str(parse_url($statusUrl, PHP_URL_QUERY), $query);
        $this->assertSame(['payment_id' => self::PAYMENT_ID], $query);
        Http::assertNothingSent();
    }

    public function test_only_new_keepz_payments_get_the_receipt_verification_marker(): void
    {
        $owner = DeliveryMan::query()->findOrFail(1);
        foreach (['keepz', 'stripe'] as $gateway) {
            $service = Mockery::mock(DeliveryManWalletService::class)->makePartial()->shouldAllowMockingProtectedMethods();
            $service->shouldReceive('paymentRedirectLink')->once()
                ->with($owner, Mockery::on(function (array $payment) use ($gateway): bool {
                    $this->assertSame($gateway, $payment['payment_method']);
                    $this->assertSame('deliveryman_collect_cash_payments', $payment['attribute']);
                    $this->assertSame(1, $payment['attribute_id']);
                    $this->assertSame($gateway === 'keepz' ? ['cash_collection_verification_version' => 1] : [], $payment['additional_data']);

                    return true;
                }))->andReturn('https://mili.ge/payment/'.$gateway.'/pay');
            $service->collectCashPaymentLink($owner, ['payment_gateway' => $gateway, 'amount' => 10, 'callback' => 'https://mili.ge/success']);
        }
    }
}
