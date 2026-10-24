<?php

namespace Modules\Rental\Services\Cart;

use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Rental\Entities\RentalCart;
use Modules\Rental\Entities\RentalCartUserData;
use Modules\Rental\Entities\Vehicle;
use Modules\Rental\Services\Cart\RentalCartUserDataService;
use Modules\Rental\Services\Vehicle\VehicleService;
use App\Services\Store\StoreService;

class RentalCartService extends BaseService
{
    private const PRICE_SCALE = 8;
    private const LINE_MEDIA_RELATIONS = ['vehicle.storage', 'vehicle.translations', 'provider.storage', 'provider.translations'];
    public function guestRowsExist(mixed $guestId): bool
    {
        return $this->ownerScopedQuery($guestId, 1)->exists();
    }
    public function deleteForUser(mixed $userId): void
    {
        $this->ownerScopedQuery($userId, 0)->delete();
    }
    public function transferGuestRows(mixed $guestId, mixed $userId): void
    {
        RentalCart::where('user_id', $guestId)->update(['user_id' => $userId, 'is_guest' => 0]);
    }
    public function findLine(mixed $cartId, array $context): ?RentalCart
    {
        return $this->ownedLineQuery($cartId, $context)->with('vehicle')->first();
    }
    public function findUserData(array $context): ?RentalCartUserData
    {
        return app(RentalCartUserDataService::class)->find($context);
    }
    public function findAvailableVehicle(mixed $vehicleId, Carbon $pickupTime): ?Vehicle
    {
        return app(VehicleService::class)->findActiveWithQuantity($vehicleId, $pickupTime);
    }
    public function add(array $data, array $context): void
    {
        $cart = new RentalCart();
        $cart->user_id = $context['user_id'];
        $cart->is_guest = $context['is_guest'];
        $cart->vehicle_id = $data['vehicle_id'];
        $cart->provider_id = $data['provider_id'];
        $cart->quantity = $data['quantity'];
        $cart->module_id = $context['module_id'];
        $cart->reel_id = Helpers::resolve_reel_vehicle_id($data['reel_id'], (int) $data['vehicle_id']);
        $cart->price = $this->roundPrice($data['price'] * $cart->quantity);
        $cart->save();
    }
    public function updateLine(RentalCart $cart, array $data, array $context): void
    {
        $cart->user_id = $context['user_id'];
        $cart->is_guest = $context['is_guest'];
        $cart->quantity = $data['quantity'];
        $cart->price = $this->roundPrice($data['price'] * $cart->quantity);
        $cart->save();
    }
    public function removeLine(mixed $cartId, array $context): void
    {
        $this->ownedLineQuery($cartId, $context)->delete();
    }
    public function removeLines(array $cartIds, array $context): void
    {
        $this->ownedLineQuery($cartIds, $context)->delete();
    }
    public function clear(array $context): void
    {
        app(RentalCartUserDataService::class)->delete($context);
        $this->ownerScopedQuery($context['user_id'], $context['is_guest'])->delete();
    }
    public function linesWithVehicles(array $context): Collection
    {
        return $this->moduleScopedQuery($context)
            ->with(['vehicle' => fn ($query) => $query->withStorage()])
            ->get();
    }
    public function calculatePrice(Vehicle $vehicle, string $rentalType, mixed $estimatedHours, mixed $distance): float
    {
        $estimatedHours = is_numeric($estimatedHours) ? (float) $estimatedHours : 0.0;
        $distance = is_numeric($distance) ? (float) $distance : 0.0;

        $base = match ($rentalType) {
            'hourly' => (float) $vehicle->hourly_price * $estimatedHours,
            'day_wise' => (float) $vehicle->day_wise_price * ((int) round($estimatedHours / 24)),
            // §3.4 — convert the measured kilometres into the rate's unit before multiplying.
            default => (float) $vehicle->distance_price
                * app(\App\Services\System\DistanceService::class)->chargeable($distance),
        };

        return $this->applyDiscount($base, $vehicle->discount_type, $vehicle->discount_price);
    }
    public function refresh(array $context, array $inputs, array $zones = []): array
    {
        $userData = $this->buildUserData($context, $inputs);

        if ($zones && ($context['zone_ids'] ?? []) && ! array_intersect($context['zone_ids'], $zones)) {
            $this->ownerScopedQuery($context['user_id'], $context['is_guest'])->delete();
        }

        $userData->total_cart_price = $this->repriceLines($context, $inputs, $userData, $zones);
        $userData->save();

        return [
            'carts' => $this->loadLines($context, $this->resolvePickupTime($inputs, $userData)),
            'user_data' => $userData,
        ];
    }
    public function resolvePickupTime(array $inputs, ?RentalCartUserData $userData): Carbon
    {
        if ($inputs['pickup_time'] ?? null) {
            return Carbon::parse($inputs['pickup_time']);
        }

        return $userData?->pickup_time ? Carbon::parse($userData->pickup_time) : now();
    }
    public function existingProviderId(array $context): mixed
    {
        return $this->moduleScopedQuery($context)->first()?->provider_id;
    }
    public function hasVehicle(mixed $vehicleId, array $context): bool
    {
        return $this->moduleScopedQuery($context)->where('vehicle_id', $vehicleId)->exists();
    }
    public function findOpenProvider(mixed $providerId, Carbon $pickupTime): ?Store
    {
        return app(StoreService::class)->findActiveOpenAt($providerId, $pickupTime);
    }
    public function rebuildFromTrip(mixed $trip, array $context, array $pickupZones = []): array
    {
        $this->ownerScopedQuery($context['user_id'], 0)
            ->where('module_id', $context['module_id'])->delete();
        app(RentalCartUserDataService::class)->deleteForCustomer($context['user_id']);

        $first = $trip->trip_details->first();
        $rentalType = $first?->rental_type ?? 'hourly';
        $estimatedHours = (float) ($first?->estimated_hours ?? $trip->estimated_hours ?? 0);
        $distance = (float) ($first?->distance ?? $trip->distance ?? 0);
        $pickupLocation = $trip->pickup_location ?? [];
        $pickupTime = Carbon::now();

        $userData = new RentalCartUserData();
        $userData->user_id = $context['user_id'];
        $userData->is_guest = 0;
        $userData->pickup_location = json_encode($pickupLocation);
        $userData->destination_location = json_encode($trip->destination_location ?? []);
        $userData->pickup_time = $pickupTime;
        $userData->rental_type = $rentalType;
        $userData->estimated_hours = $estimatedHours;
        $userData->distance = $distance;
        $userData->destination_time = 0;
        $userData->total_cart_price = 0;
        $userData->save();

        $added = [];
        $unavailable = [];

        foreach ($trip->trip_details as $detail) {
            $vehicleId = (int) $detail->vehicle_id;
            $quantity = max(1, (int) ($detail->quantity ?? 1));
            $detailType = $detail->rental_type ?: $rentalType;
            $vehicle = $this->findAvailableVehicle($vehicleId, $pickupTime);

            if (! $vehicle) {
                $unavailable[] = $this->unavailableRow($vehicleId, $detail->vehicle?->name, 'vehicle_not_found');

                continue;
            }

            $rejection = $this->additionRejection(
                $vehicle,
                ['vehicle_id' => $vehicleId, 'quantity' => $quantity, 'rental_type' => $detailType],
                $context,
                $pickupZones,
                $userData,
                $pickupTime
            );

            if ($rejection) {
                $unavailable[] = $this->unavailableRow($vehicleId, $vehicle->name, $rejection['code']);

                continue;
            }

            $this->add([
                'vehicle_id' => $vehicleId,
                'provider_id' => $vehicle->provider_id,
                'quantity' => $quantity,
                'reel_id' => null,
                'price' => $this->calculatePrice($vehicle, $detailType, $estimatedHours, $distance),
            ], $context);

            $added[] = ['id' => $vehicleId, 'name' => $vehicle->name];
        }

        return [
            'cart_count' => $this->ownerScopedQuery($context['user_id'], 0)
                ->where('module_id', $context['module_id'])->count(),
            'added_count' => count($added),
            'skipped_count' => count($unavailable),
            'unavailable_items' => $unavailable,
            'skipped_items' => [],
        ];
    }
    public function additionRejection(mixed $vehicle, array $data, array $context, array $pickupZones, mixed $userData, Carbon $pickupTime): ?array
    {
        if (! $vehicle) {
            return ['code' => 'cart_item', 'message' => translate('Vehicle currently unavailable!')];
        }

        if ($this->hasVehicle($data['vehicle_id'], $context)) {
            return ['code' => 'cart_item', 'message' => translate('Vehicle already exists')];
        }

        if ($vehicle->total_vehicle_count <= 0) {
            return ['code' => 'cart_item', 'message' => translate('This vehicle is not available on this pickup time')];
        }

        if ($vehicle->total_vehicle_count < $data['quantity']) {
            return ['code' => 'cart_item', 'message' => translate('messages.Max vehicle available quantity is') . ' ' . $vehicle->total_vehicle_count];
        }

        $store = $this->findOpenProvider($vehicle->provider_id, $pickupTime);

        if (! $store) {
            return ['code' => 'cart_item', 'message' => translate('No data found')];
        }

        $providerZones = $store->pickupZoneIds();

        if (! $providerZones) {
            return ['code' => 'cart_item', 'message' => translate('Provider pickup zone not found')];
        }

        if ($pickupZones && ! array_intersect($providerZones, $pickupZones)) {
            return ['code' => 'cart_item', 'message' => translate('This vehicle is not available for this pickup location. Please choose a different vehicle or location')];
        }

        if (! $store->open) {
            return ['code' => 'cart_item', 'message' => translate('Provider is closed at trip time')];
        }

        if ($unsupported = $this->unsupportedRentalType($vehicle, $data['rental_type'] ?? null)) {
            return ['code' => 'cart_item', 'message' => $vehicle->name . ': ' . translate($unsupported)];
        }

        $providerId = $this->existingProviderId($context);

        if ($providerId && $userData?->rental_type && $userData->rental_type !== ($data['rental_type'] ?? null)) {
            return ['code' => 'cart_item', 'message' => $vehicle->name . ' ' . translate('does_not_support') . ' ' . translate($userData->rental_type)
                . ' ' . translate('messages.You cannot add a vehicle with a different rental type')];
        }

        if ($providerId && $providerId != $vehicle->provider_id) {
            return ['code' => 'cart_item', 'message' => translate('You can not add different provider vehicles')];
        }

        return null;
    }
    public function unsupportedRentalType(mixed $vehicle, ?string $rentalType): ?string
    {
        return match (true) {
            $rentalType === 'hourly' && $vehicle?->trip_hourly != 1 => 'This vehicle does not offer hourly rental. You cannot add a vehicle with a different rental type.',
            $rentalType === 'distance_wise' && $vehicle?->trip_distance != 1 => 'This vehicle does not offer distance-wise rental. You cannot add a vehicle with a different rental type.',
            $rentalType === 'day_wise' && $vehicle?->trip_day_wise != 1 => 'This vehicle does not offer day-wise rental. You cannot add a vehicle with a different rental type.',
            default => null,
        };
    }
    private function ownedLineQuery(mixed $cartIds, array $context): Builder
    {
        return RentalCart::whereIn('id', (array) $cartIds)
            ->where('user_id', $context['user_id'])
            ->where('is_guest', $context['is_guest']);
    }
    private function ownerScopedQuery(mixed $userId, mixed $isGuest): Builder
    {
        return RentalCart::where('user_id', $userId)->where('is_guest', $isGuest);
    }
    private function moduleScopedQuery(array $context): Builder
    {
        return $this->ownerScopedQuery($context['user_id'], $context['is_guest'])->where('module_id', $context['module_id']);
    }
    private function unavailableRow(int $vehicleId, ?string $name, ?string $code): array
    {
        return [
            'id' => $vehicleId,
            'name' => $name,
            'code' => $code,
            'message' => trim(($name ?: translate('messages.Car')) . ' ' . translate('messages.Is not available')),
        ];
    }
    private function buildUserData(array $context, array $inputs): RentalCartUserData
    {
        $userData = app(RentalCartUserDataService::class)->findOrNew($context);

        $userData->user_id = $context['user_id'];
        $userData->is_guest = $context['is_guest'];
        $userData->pickup_location = json_encode($inputs['pickup_location'] ?? $userData->pickup_location);
        $userData->destination_location = json_encode($inputs['destination_location'] ?? $userData->destination_location);
        $userData->pickup_time = ($inputs['pickup_time'] ?? null)
            ? Carbon::parse($inputs['pickup_time'])
            : ($userData->pickup_time ?? now());
        $userData->rental_type = $inputs['rental_type'] ?? $userData->rental_type ?? 'hourly';
        $userData->estimated_hours = $inputs['estimated_hours'] ?? $userData->estimated_hours ?? 0;
        $userData->distance = $inputs['distance'] ?? $userData->distance ?? 0;
        $userData->destination_time = $inputs['destination_time'] ?? $userData->destination_time ?? 0;

        return $userData;
    }
    private function applyDiscount(mixed $price, mixed $discountType, mixed $discount = 0): float
    {
        if ($price > 0 && $discount > 0) {
            $discount = $discountType === 'percent' ? ($price * $discount) / 100 : $discount;
        }

        return $price - $discount;
    }
    private function repriceLines(array $context, array $inputs, RentalCartUserData $userData, array $zones): float
    {
        $total = 0;

        $lines = $this->moduleScopedQuery($context)
            ->with([
                'vehicle' => fn ($query) => $query->withCount('vehicleIdentities as total_vehicle_count'),
                'provider:id,name,address,tax',
                'provider.discount',
            ])
            ->get();

        foreach ($lines as $line) {
            if ($line->vehicle && $line->vehicle->status == 1) {
                $line->user_id = $context['user_id'];
                $line->is_guest = $context['is_guest'];
                $line->price = $this->roundPrice($this->calculatePrice(
                    $line->vehicle,
                    $inputs['rental_type'] ?? $userData->rental_type,
                    $inputs['estimated_hours'] ?? $userData->estimated_hours,
                    $inputs['distance'] ?? $userData->distance
                ) * $line->quantity);
                $line->save();
                $total += $line->price;
            } else {
                $line->delete();
            }

        }

        $this->deleteUnavailableLines($lines, $zones);

        return $total;
    }
    private function roundPrice(mixed $price): float
    {
        return round((float) $price, self::PRICE_SCALE);
    }
    private function deleteUnavailableLines(Collection $lines, array $zones): void
    {
        $vehicleIds = $lines->pluck('vehicle_id')->filter()->unique();

        if ($vehicleIds->isEmpty()) {
            return;
        }

        $serviceable = app(VehicleService::class)->getServiceableIds($vehicleIds->all(), $zones);

        foreach ($lines as $line) {
            if (! in_array($line->vehicle_id, $serviceable)) {
                $line->delete();
            }
        }
    }
    private function loadLines(array $context, Carbon $pickupTime): Collection
    {
        $lines = $this->moduleScopedQuery($context)
            ->with([
                'vehicle' => fn ($query) => $query->withCount([
                    'vehicleIdentities as total_vehicle_count' => fn ($builder) => $builder->DynamicVehicleQuantity($pickupTime),
                ]),
                'provider:id,name,address,tax,pickup_zone_id',
                'provider.storeConfig:id,store_id,verified_seller',
                'provider.discount' => fn ($query) => $query->validate(),
            ])
            ->get();

        $lines->loadMissing(self::LINE_MEDIA_RELATIONS);

        $lines->each(function ($line) {
            // `thumbnail_full_url` is an accessor, not a column, and Vehicle stopped carrying
            // an $appends list when the module moved to resources -- so a cart line shipped the
            // bare `thumbnail` filename with nothing that resolved it. Every other rental
            // endpoint spells the key out in a resource; this one hands the models over as they
            // are, so it appends here instead. Per instance, off the `vehicle.storage` already
            // loaded above, rather than on the model: appending there would put a storage lookup
            // on every vehicle payload in the app to fix a key only this endpoint is short of.
            $line->vehicle?->append('thumbnail_full_url');

            if (! $line->provider) {
                return;
            }

            $line->provider['verified_seller'] = Helpers::get_verified_seller_status($line->provider, $line->provider->storeConfig);
            $line->provider->pickup_zone_id = $line->provider->pickupZoneIds();
        });

        return $lines;
    }
}
