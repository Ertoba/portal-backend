<?php

namespace Tests\Unit;

use App\Services\Order\TrustedCustomerDeliveryDistance;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'routes' => [['distanceMeters' => 12842]],
        ], 200)]);

        $distance = app(TrustedCustomerDeliveryDistance::class)
            ->routeKilometres(41.69, 44.80, 41.71, 44.79);

        $this->assertSame(12.842, $distance);
        Http::assertSent(fn ($request) =>
            $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters')
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
