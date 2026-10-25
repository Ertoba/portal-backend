<?php

namespace App\Services\Order;

use App\Models\Store;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authoritative route distance for customer-facing checkout and order placement.
 *
 * Never trust the client's distance for pricing: measure the store-to-customer
 * (or parcel pickup-to-recipient) driving route on the server. Both checkout
 * summary and place-order call this before the shared delivery fee pipeline.
 */
final class TrustedCustomerDeliveryDistance
{
    private const ROUTES_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function apply(Request $request): void
    {
        if (! in_array($request->input('order_type'), ['delivery', 'parcel'], true)) {
            return;
        }

        $origin = null;
        $destination = null;

        if ($request->input('order_type') === 'parcel') {
            $origin = [$request->input('latitude'), $request->input('longitude')];
            $receiver = $request->input('receiver_details');
            $receiver = is_string($receiver) ? json_decode($receiver, true) : $receiver;

            if (is_array($receiver)) {
                $destination = [$receiver['latitude'] ?? null, $receiver['longitude'] ?? null];
            }
        } else {
            $store = Store::query()->find($request->input('store_id'));
            if ($store) {
                $origin = [$store->latitude, $store->longitude];
                $destination = [$request->input('latitude'), $request->input('longitude')];
            }
        }

        if (! is_array($origin) || ! is_array($destination)) {
            $this->fail(422, 'Valid pickup and delivery coordinates are required.');
        }

        $request->merge([
            'distance' => $this->routeKilometres($origin[0], $origin[1], $destination[0], $destination[1]),
        ]);
    }

    /**
     * Returns the provider-measured driving distance, always in kilometres.
     * Public to allow independent, DB-free tests of route integrity.
     */
    public function routeKilometres(mixed $originLat, mixed $originLng, mixed $destinationLat, mixed $destinationLng): float
    {
        $from = [
            'latitude' => $this->coordinate($originLat, -90, 90),
            'longitude' => $this->coordinate($originLng, -180, 180),
        ];
        $to = [
            'latitude' => $this->coordinate($destinationLat, -90, 90),
            'longitude' => $this->coordinate($destinationLng, -180, 180),
        ];

        $apiKey = trim((string) config('services.google_routes.key', ''));
        if ($apiKey === '') {
            Log::error('Customer delivery distance verification is not configured');
            $this->fail(503, 'Delivery distance verification is temporarily unavailable.');
        }

        // Rounded to ~1 metre. Only geographic coordinates enter the cache key;
        // neither client distance nor customer-supplied pricing inputs do.
        $cacheKey = 'customer-route-km:v1:'.hash('sha256', implode(':', [
            round($from['latitude'], 5), round($from['longitude'], 5),
            round($to['latitude'], 5), round($to['longitude'], 5),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($apiKey, $from, $to): float {
            try {
                $response = Http::acceptJson()
                    ->withHeaders([
                        'X-Goog-Api-Key' => $apiKey,
                        'X-Goog-FieldMask' => 'routes.distanceMeters',
                    ])
                    ->timeout(8)
                    ->post(self::ROUTES_URL, [
                        'origin' => ['location' => ['latLng' => $from]],
                        'destination' => ['location' => ['latLng' => $to]],
                        'travelMode' => 'DRIVE',
                        'computeAlternativeRoutes' => false,
                        'units' => 'METRIC',
                    ]);
            } catch (Throwable $exception) {
                Log::warning('Delivery route provider connection failed', [
                    'exception_type' => get_class($exception),
                ]);
                $this->fail(503, 'Delivery distance verification is temporarily unavailable.');
            }

            $metres = $response->successful()
                ? data_get($response->json(), 'routes.0.distanceMeters')
                : null;

            // A missing route must NEVER be interpreted as zero-distance delivery.
            if (! is_numeric($metres) || (float) $metres < 0 || ! is_finite((float) $metres)) {
                Log::warning('Delivery route provider returned no usable route', [
                    'http_status' => $response->status(),
                ]);
                $this->fail(503, 'A delivery route could not be verified for this address.');
            }

            return round(((float) $metres) / 1000, 4);
        });
    }

    private function coordinate(mixed $value, float $minimum, float $maximum): float
    {
        if (! is_numeric($value)) {
            $this->fail(422, 'Valid pickup and delivery coordinates are required.');
        }

        $number = (float) $value;
        if (! is_finite($number) || $number < $minimum || $number > $maximum) {
            $this->fail(422, 'Valid pickup and delivery coordinates are required.');
        }

        return $number;
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'errors' => [['code' => 'distance', 'message' => $message]],
        ], $status));
    }
}
