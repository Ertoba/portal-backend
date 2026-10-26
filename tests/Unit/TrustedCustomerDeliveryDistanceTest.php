<?php

namespace Tests\Unit;

use App\Services\Order\TrustedCustomerDeliveryDistance;
use App\Models\Store;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TrustedCustomerDeliveryDistanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google_routes.key', 'testing-only-key');
        Cache::flush();
    }

    public function test_provider_distance_is_used_instead_of_client_submitted_distance(): void
    {
        Http::fake(['routes.googleapis.com/*' => Http::response([
            'routes' => [['distanceMeters' => 12842, 'duration' => '1120s']],
        ], 200)]);

        $distance = app(TrustedCustomerDeliveryDistance::class)
            ->routeKilometres(41.69, 44.80, 41.71, 44.79);

        $this->assertSame(12.842, $distance);
        Http::assertSent(fn ($request) =>
            $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters,routes.duration')
            && $request['origin']['location']['latLng']['latitude'] === 41.69
            && $request['travelMode'] === 'DRIVE'
        );
    }

    public function test_a_provider_error_fails_closed_instead_of_pricing_zero(): void
    {
        Http::fake(['routes.googleapis.com/*' => Http::response(['error' => 'unavailable'], 503)]);

        try {
            app(TrustedCustomerDeliveryDistance::class)->routeKilometres(41, 44, 42, 45);
            $this->fail('A failed route lookup must not return a distance.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
        }
    }

    public function test_missing_server_key_fails_closed_without_contacting_provider(): void
    {
        config()->set('services.google_routes.key', '');
        Http::fake();

        $this->expectException(HttpResponseException::class);
        app(TrustedCustomerDeliveryDistance::class)->routeKilometres(41, 44, 42, 45);
        Http::assertNothingSent();
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->expectException(HttpResponseException::class);
        app(TrustedCustomerDeliveryDistance::class)->routeKilometres('untrusted', 44, 42, 45);
    }

    public function test_parcel_request_ignores_a_forged_zero_distance(): void
    {
        Http::fake(['routes.googleapis.com/*' => Http::response([
            'routes' => [['distanceMeters' => 7500, 'duration' => '840s']],
        ], 200)]);

        $request = Request::create('/api/v1/customer/order/place', 'POST', [
            'order_type' => 'parcel',
            'latitude' => '41.5',
            'longitude' => '44.5',
            'distance' => 0,
            'delivery_duration' => 1,
            'receiver_details' => json_encode([
                'latitude' => 41.6,
                'longitude' => 44.6,
                'zone_id' => 1,
            ]),
        ]);

        app(TrustedCustomerDeliveryDistance::class)->apply($request);

        $this->assertSame(7.5, $request->input('distance'));
        $this->assertSame(840, $request->input('delivery_duration'));

        Http::assertSent(fn ($outgoing) =>
            $outgoing['origin']['location']['latLng']['latitude'] === 41.5
            && $outgoing['destination']['location']['latLng']['longitude'] === 44.6
        );
    }

    public function test_missing_provider_duration_clears_untrusted_client_eta(): void
    {
        Http::fake(['routes.googleapis.com/*' => Http::response([
            'routes' => [['distanceMeters' => 2400]],
        ], 200)]);

        $request = Request::create('/api/v1/customer/order/place', 'POST', [
            'order_type' => 'parcel',
            'latitude' => 41.5,
            'longitude' => 44.5,
            'distance' => 0,
            'delivery_duration' => 1,
            'receiver_details' => json_encode([
                'latitude' => 41.6, 'longitude' => 44.6,
            ]),
        ]);

        app(TrustedCustomerDeliveryDistance::class)->apply($request);

        $this->assertSame(2.4, $request->input('distance'));
        $this->assertNull($request->input('delivery_duration'));
    }

    public function test_takeaway_keeps_original_input_and_skips_paid_route_lookup(): void
    {
        Http::fake();
        $request = Request::create('/api/v1/customer/order/place', 'POST', [
            'order_type' => 'take_away',
            'distance' => 0,
        ]);

        app(TrustedCustomerDeliveryDistance::class)->apply($request);

        $this->assertSame(0, $request->input('distance'));
        Http::assertNothingSent();
    }

    public function test_parcel_missing_receiver_is_rejected_instead_of_trusting_client_distance(): void
    {
        Http::fake();
        $request = Request::create('/api/v1/customer/order/place', 'POST', [
            'order_type' => 'parcel',
            'latitude' => 41.5,
            'longitude' => 44.5,
            'distance' => 0,
            'receiver_details' => '{}',
        ]);

        $this->expectException(HttpResponseException::class);
        app(TrustedCustomerDeliveryDistance::class)->apply($request);
        Http::assertNothingSent();
    }

    public function test_pos_distance_is_remeasured_from_database_store_coordinates_not_form_value(): void
    {
        Http::fake(['routes.googleapis.com/*' => Http::response([
            'routes' => [['distanceMeters' => 18400]],
        ], 200)]);

        $store = new Store();
        $store->latitude = 41.7300;
        $store->longitude = 44.7900;

        $distance = app(TrustedCustomerDeliveryDistance::class)->forStoreAddress(
            $store,
            ['latitude' => 41.7400, 'longitude' => 44.8300, 'distance' => 0],
        );

        $this->assertSame(18.4, $distance);
        Http::assertSent(fn ($request) =>
            $request['origin']['location']['latLng']['latitude'] === 41.73
            && $request['origin']['location']['latLng']['longitude'] === 44.79
            && $request['destination']['location']['latLng']['latitude'] === 41.74
        );
    }

    public function test_pos_missing_pinned_address_fails_closed(): void
    {
        Http::fake();

        $store = new Store();
        $store->latitude = 41.73;
        $store->longitude = 44.79;

        try {
            app(TrustedCustomerDeliveryDistance::class)->forStoreAddress(
                $store,
                ['distance' => 0],
            );
            $this->fail('POS should not accept a forged distance without coordinates');
        } catch (HttpResponseException $exception) {
            $this->assertSame(422, $exception->getResponse()->getStatusCode());
        }

        Http::assertNothingSent();
    }

    public function test_valid_route_is_cached_for_same_geographic_pair(): void
    {
        Http::fake(['routes.googleapis.com/*' => Http::response([
            'routes' => [['distanceMeters' => 2000]],
        ], 200)]);

        $service = app(TrustedCustomerDeliveryDistance::class);
        $this->assertSame(2.0, $service->routeKilometres(41, 44, 42, 45));
        $this->assertSame(2.0, $service->routeKilometres(41, 44, 42, 45));
        Http::assertSentCount(1);
    }
}
