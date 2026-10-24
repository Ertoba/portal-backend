<?php

namespace Modules\Rental\Services\Vehicle;

use Illuminate\Database\Eloquent\Builder;
use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Services\BaseService;
use Illuminate\Support\Facades\Schema;
use App\Traits\Customer\PersonalizationTrait;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Vehicle;
use App\Support\Storage\FileStorage;

class VehicleService extends BaseService
{
    use PersonalizationTrait;
    private const IMAGE_DIR = 'vehicle/';
    private const DEFAULT_THUMBNAIL = 'def.png';
    private const VENDOR_RELATIONS = [
        'vehicleIdentities', 'provider', 'category', 'brand', 'translations',
        'storage', 'category.storage', 'brand.storage',
    ];
    private const SUGGESTION_LIMIT = 10;
    private const FORMATTER_RELATIONS = ['storage', 'provider.storage', 'provider.storeConfig'];
    private const PRICE_COLUMN = [
        'distance_wise' => 'distance_price',
        'hourly' => 'hourly_price',
        'day_wise' => 'day_wise_price',
    ];
    public function getMinimumPrices(): array
    {
        return [
            'distance' => ApiCache::remember('vehicle_price', 'dis_min_conf', fn () => Vehicle::where('distance_price', '>', '0')->min('distance_price')),
            'hourly' => ApiCache::remember('vehicle_price', 'hour_min_conf', fn () => Vehicle::where('hourly_price', '>', '0')->min('hourly_price')),
            'day_wise' => Schema::hasColumn('vehicles', 'day_wise_price')
                ? ApiCache::remember('vehicle_price', 'day_wise_min_conf', fn () => Vehicle::where('day_wise_price', '>', '0')->min('day_wise_price'))
                : 0,
        ];
    }
    public function getTopRatedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $query = Vehicle::active()
            ->tap(fn ($builder) => $this->scopeToZones($builder, $filters['zone_id'] ?? []))
            ->with([
                'provider:id,name,address,tax',
                'provider.discount' => fn ($discount) => $discount->validate(),
            ])
            ->withCount('vehicleIdentities as total_vehicle_count')
            ->orderBy('avg_rating', 'desc')
            ->orderBy('total_trip', 'desc')
            ->latest();

        $query = $this->personalise($query, $filters);

        return $this->loadListRelations($query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate)));
    }
    public function getSearchList(array $filters = [], array $paginate = []): array
    {
        $data = $this->searchQueryWithPrices($filters);

        return [
            'vehicles' => $this->loadListRelations($data['vehicles']->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate))),
            'max_price' => (int) $data['max_price'],
            'min_price' => (int) $data['min_price'],
        ];
    }
    public function getSuggestionList(array $filters = []): array
    {
        return Vehicle::active()
            ->tap(fn ($builder) => $this->scopeToZones($builder, $filters['zone_id'] ?? []))
            ->with('brand:id,name')
            ->tap(fn ($builder) => $this->applyNameSearch($builder, $filters['name'] ?? null))
            ->latest()
            ->take(self::SUGGESTION_LIMIT)
            ->get(['id', 'brand_id', 'name'])
            ->map(fn ($vehicle) => $vehicle?->brand?->name
                ? $vehicle->brand->name . ' - ' . $vehicle->name
                : $vehicle->name)
            ->values()
            ->all();
    }
    public function findDetail(mixed $identifier): ?Vehicle
    {
        return Vehicle::withStorage()
            ->with(['brand.storage', 'category.storage'])
            ->where(fn ($query) => $query->where('id', $identifier)->orWhere('slug', $identifier))
            ->withCount('vehicleIdentities as total_vehicles')
            ->with([
                'provider' => fn ($query) => $query
                    ->select('id', 'name', 'slug', 'logo', 'cover_photo', 'rating', 'address', 'delivery_time')
                    ->with('storage')
                    ->withCount(['vehicle_identity as provider_total_vehicle_count']),
                'provider.storeConfig:id,store_id,verified_seller',
                'brand:id,name,image',
                'provider.discount' => fn ($query) => $query->validate(),
            ])
            ->first()
            ?->loadMissing(['seoData', 'storage']);
    }
    public function getVendorList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $paginator = Vehicle::with(self::VENDOR_RELATIONS)
            ->where('provider_id', $filters['provider_id'] ?? null)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($group) => $this->matchVendorName($group, $search)
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->when($filters['brand_id'] ?? null, fn ($query, $value) => $query->where('brand_id', $value))
            ->when(isset($filters['air_condition']), fn ($query) => $query->where('air_condition', $filters['air_condition']))
            ->when($filters['transmission_type'] ?? null, fn ($query, $value) => $query->where('transmission_type', $value))
            ->when($filters['vehicle_type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->when($filters['fuel_type'] ?? null, fn ($query, $value) => $query->where('fuel_type', $value))
            ->when($filters['seating_capacity'] ?? null, fn ($query, $range) => $query->where(
                fn ($group) => $this->applySeatingCapacity($group, [$range])
            ))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $this->loadListRelations($paginator);
    }
    public function findForProvider(mixed $id, mixed $providerId): ?Vehicle
    {
        return $this->find($id, $providerId);
    }
    public function findVendorDetail(mixed $id, mixed $providerId): ?Vehicle
    {
        return $this->providerVehicleQuery($providerId, ['seoData'])->find($id);
    }
    public function findForEdit(mixed $id, mixed $providerId): ?Vehicle
    {
        return $this->providerVehicleQuery($providerId, ['translations'])
            ->withoutGlobalScope('translate')
            ->find($id);
    }
    public function vehicleLimitMessage(Store $store): ?string
    {
        if (! $store->item_section) {
            return translate('Your vehicle upload limit is over');
        }

        if ($store->store_business_model === 'unsubscribed') {
            return translate('You are not subscribed to any package');
        }

        if ($store->store_business_model !== 'subscription') {
            return null;
        }

        $subscription = $store->store_sub;

        if (! $subscription) {
            return translate('You are not subscribed to any package');
        }

        if ($subscription->max_product !== 'unlimited' && $subscription->max_product > 0
            && Vehicle::where('provider_id', $store->id)->count() + 1 >= $subscription->max_product) {
            $store->item_section = 0;
            $store->save();
        }

        return null;
    }
    public function create(array $data): Vehicle
    {
        $media = $this->uploadMedia($data);

        return DB::transaction(function () use ($data, $media) {
            $vehicle = new Vehicle();
            $this->fill($vehicle, $data, $media);
            $vehicle->save();

            $this->syncIdentities($vehicle, $data['identities'], false);
            $this->syncTranslations($vehicle, $data['translations']);

            return $vehicle;
        });
    }
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $media = $this->uploadMedia($data, $vehicle);

        return DB::transaction(function () use ($vehicle, $data, $media) {
            $this->fill($vehicle, $data, $media);
            $vehicle->update();

            $this->syncIdentities($vehicle, $data['identities'], true);
            $this->syncTranslations($vehicle, $data['translations']);

            return $vehicle;
        });
    }
    public function updateStatus(Vehicle $vehicle): void
    {
        $vehicle->update(['status' => ! $vehicle->status]);
    }
    public function toggleNewTag(Vehicle $vehicle): void
    {
        $vehicle->update(['new_tag' => ! $vehicle->new_tag]);
    }
    public function delete(Vehicle $vehicle): bool
    {
        $thumbnail = $vehicle->thumbnail;

        $deleted = DB::transaction(function () use ($vehicle) {
            $vehicle->translations()->delete();

            return (bool) $vehicle->delete();
        });

        if ($deleted && $thumbnail) {
            FileStorage::delete(self::IMAGE_DIR, $thumbnail);
        }

        return $deleted;
    }
    public function existsForProvider(mixed $vehicleId, mixed $providerId): bool
    {
        return Vehicle::where('id', $vehicleId)->where('provider_id', $providerId)->exists();
    }
    public function query(): mixed
    {
        return Vehicle::query();
    }
    public function findActiveWithQuantity(mixed $vehicleId, mixed $pickupTime): ?Vehicle
    {
        return Vehicle::where('id', $vehicleId)
            ->active()
            ->withCount([
                'vehicleIdentities as total_vehicle_count' => fn ($query) => $query->DynamicVehicleQuantity($pickupTime),
            ])
            ->first();
    }
    public function getServiceableIds(array $vehicleIds, mixed $zones): array
    {
        return Vehicle::whereIn('id', $vehicleIds)
            ->when($zones, fn ($query) => $query->whereHas('provider', fn ($provider) => $provider
                ->active()
                ->servingPickupZones($zones)))
            ->pluck('id')
            ->all();
    }
    public function find(mixed $id, mixed $providerId = null): ?Vehicle
    {
        return Vehicle::when($providerId, fn ($query, $provider) => $query->where('provider_id', $provider))->find($id);
    }
    private function providerVehicleQuery(mixed $providerId, array $extraRelations = []): Builder
    {
        return Vehicle::withStorage()
            ->with(array_merge(['provider.storage', 'category.storage', 'brand.storage', 'vehicleIdentities'], $extraRelations))
            ->where('provider_id', $providerId);
    }
    private function loadListRelations(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $paginator->getCollection()->loadMissing(self::FORMATTER_RELATIONS);

        return $paginator;
    }
    private function searchQueryWithPrices(array $filters): array
    {
        [$maxPrice, $minPrice] = $this->priceRange($filters);

        $query = Vehicle::active()
            ->tap(fn ($builder) => $this->scopeToZones($builder, $filters['pickup_zones'] ?? []))
            ->with([
                'provider:id,name,address,tax',
                'vehicleIdentities.vehicle_trip_details',
                'provider.discount' => fn ($discount) => $discount->validate(),
            ]);

        $query = $filters['date'] ?? null
            ? $query->withCount([
                'vehicleIdentities as total_vehicle_count' => fn ($builder) => $builder->DynamicVehicleQuantity(Carbon::parse($filters['date'])),
            ])->having('total_vehicle_count', '>', 0)
            : $query->withCount('vehicleIdentities as total_vehicle_count');

        $this->applyScheduleFilter($query, $filters);
        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return ['vehicles' => $this->personalise($query, $filters), 'max_price' => $maxPrice, 'min_price' => $minPrice];
    }
    /**
     * Drops a provider that could not actually take this trip: a `date` in the future makes this a
     * scheduled pickup, and a provider with schedule_order off (TripService enforces the same rule
     * at booking time) would refuse it there, so it should not surface here as if it could serve it.
     *
     * Store::schedule_order already folds in the admin-wide toggle (getScheduleOrderAttribute()),
     * but that is a PHP accessor and this needs the raw column for whereHas() -- so when the
     * admin-wide toggle itself is off, no provider's column value matters and every one is excluded.
     */
    private function applyScheduleFilter(mixed $query, array $filters): void
    {
        $date = $filters['date'] ?? null;

        if (! $date || ! Carbon::parse($date)->isFuture()) {
            return;
        }

        if (! Helpers::schedule_order()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('provider', fn ($provider) => $provider->where('schedule_order', 1));
    }
    private function applyFilters(mixed $query, array $filters): void
    {
        $tripType = $filters['trip_type'] ?? null;
        $maxPrice = $filters['max_price'] ?? null;
        $minPrice = $filters['min_price'] ?? 0;
        $priceColumn = self::PRICE_COLUMN[$tripType] ?? null;

        $query->when($filters['provider_id'] ?? null, fn ($builder, $providerId) => $builder->where('provider_id', $providerId))
            ->when($tripType === 'hourly', fn ($builder) => $builder->where('trip_hourly', 1))
            ->when($tripType === 'distance_wise', fn ($builder) => $builder->where('trip_distance', 1))
            ->when($tripType === 'day_wise', fn ($builder) => $builder->where('trip_day_wise', 1))
            ->when($priceColumn && $maxPrice > 0, fn ($builder) => $builder->whereBetween($priceColumn, [$minPrice, $maxPrice]))
            ->when($tripType === 'provider_wise' && $maxPrice > 0, fn ($builder) => $builder->where(
                fn ($group) => $group->whereBetween('hourly_price', [$minPrice, $maxPrice])
                    ->orWhereBetween('distance_price', [$minPrice, $maxPrice])
            ));

        $this->applyNameSearch($query, $filters['name'] ?? null);

        $query->when($filters['brand_ids'] ?? null, fn ($builder, $ids) => $builder->whereIn('brand_id', $ids))
            ->when($filters['category_ids'] ?? null, fn ($builder, $ids) => $builder->whereIn('category_id', $ids))
            ->when($filters['seating_capacity'] ?? null, fn ($builder, $ranges) => $builder->where(
                fn ($group) => $this->applySeatingCapacity($group, $ranges)
            ))
            ->when($filters['air_condition'] ?? null, fn ($builder) => $builder->where('air_condition', 1))
            ->when($filters['no_air_condition'] ?? null, fn ($builder) => $builder->where('air_condition', 0))
            ->when($filters['transmission_type'] ?? null, fn ($builder, $type) => $builder->where('transmission_type', $type))
            ->when($filters['vehicle_type'] ?? null, fn ($builder, $type) => $builder->where('type', $type))
            ->when($filters['fuel_type'] ?? null, fn ($builder, $type) => $builder->where('fuel_type', $type));
    }
    private function applySorting(mixed $query, array $filters): void
    {
        $sort = in_array($filters['sortby_price'] ?? null, ['asc', 'desc'], true) ? $filters['sortby_price'] : null;
        $tripType = $filters['trip_type'] ?? null;
        $topRated = ($filters['top_rated'] ?? null) == 1;

        if ($sort && $priceColumn = self::PRICE_COLUMN[$tripType] ?? null) {
            $query->orderBy($priceColumn, $sort);
        } elseif ($sort && $tripType === 'provider_wise') {
            $query->select('*')
                ->selectRaw('LEAST(
                        CASE WHEN hourly_price IS NULL OR hourly_price = 0 THEN 999999999 ELSE hourly_price END,
                        CASE WHEN distance_price IS NULL OR distance_price = 0 THEN 999999999 ELSE distance_price END,
                        CASE WHEN day_wise_price IS NULL OR day_wise_price = 0 THEN 999999999 ELSE day_wise_price END
                    ) as min_price')
                ->orderBy('min_price', $sort);
        }

        if ($topRated) {
            $query->orderBy('avg_rating', 'desc')->orderBy('total_trip', 'desc');
        }

        if (! $sort && ! $topRated) {
            $query->latest();
        }
    }
    private function fill(Vehicle $vehicle, array $data, array $media): void
    {
        $vehicle->name = $data['name'];
        $vehicle->description = $data['description'];
        $vehicle->zone_id = $data['zone_id'];
        $vehicle->provider_id = $data['provider_id'];
        $vehicle->brand_id = $data['brand_id'];
        $vehicle->category_id = $data['category_id'];
        $vehicle->model = $data['model'];
        $vehicle->type = $data['type'];
        $vehicle->engine_capacity = $data['engine_capacity'];
        $vehicle->engine_power = $data['engine_power'];
        $vehicle->seating_capacity = $data['seating_capacity'];
        $vehicle->air_condition = $data['air_condition'];
        $vehicle->multiple_vehicles = $data['multiple_vehicles'];
        $vehicle->fuel_type = $data['fuel_type'];
        $vehicle->transmission_type = $data['transmission_type'];
        $vehicle->trip_hourly = $data['trip_hourly'];
        $vehicle->trip_distance = $data['trip_distance'];
        $vehicle->trip_day_wise = $data['trip_day_wise'];
        $vehicle->hourly_price = $data['hourly_price'];
        $vehicle->distance_price = $data['distance_price'];
        $vehicle->day_wise_price = $data['day_wise_price'];
        $vehicle->discount_price = $data['discount_price'];
        $vehicle->discount_type = $data['discount_type'];
        $vehicle->tag = json_encode($data['tag']);
        $vehicle->thumbnail = $media['thumbnail'];
        $vehicle->images = $media['images'];
        $vehicle->documents = $media['documents'];
    }
    private function uploadMedia(array $data, ?Vehicle $vehicle = null): array
    {
        $thumbnail = $data['thumbnail_file']
            ? ($vehicle
                ? FileStorage::update(self::IMAGE_DIR, $vehicle->thumbnail, $data['thumbnail_file'])
                : FileStorage::upload(self::IMAGE_DIR, $data['thumbnail_file']))
            : ($vehicle?->thumbnail ?? self::DEFAULT_THUMBNAIL);

        $images = [];
        foreach (array_filter($data['image_files'] ?? []) as $image) {
            $images[] = ['img' => FileStorage::upload(self::IMAGE_DIR, $image), 'storage' => FileStorage::getDisk()];
        }

        $documents = [];
        foreach (array_filter($data['document_files'] ?? []) as $document) {
            $documents[] = [
                'img' => FileStorage::upload(self::IMAGE_DIR, $document),
                'storage' => FileStorage::getDisk(),
            ];
        }

        return [
            'thumbnail' => $thumbnail,
            'images' => json_encode($images ?: ($vehicle && ! ($data['image_files'] ?? []) ? $this->storedMedia($vehicle->images) : [])),
            'documents' => json_encode($documents ?: ($vehicle && ! ($data['document_files'] ?? []) ? $this->storedMedia($vehicle->documents) : [])),
        ];
    }
    private function storedMedia(mixed $stored): array
    {
        if (is_array($stored)) {
            return $stored;
        }

        $decoded = json_decode((string) $stored, true);

        return is_array($decoded) ? $decoded : [];
    }
    private function syncIdentities(Vehicle $vehicle, array $identities, bool $pruneMissing): void
    {
        $vins = [];

        foreach ($identities as $identity) {
            $vins[] = $identity['vin_number'];

            $vehicle->vehicleIdentities()->updateOrCreate(
                ['vin_number' => $identity['vin_number']],
                ['provider_id' => $vehicle->provider_id, 'license_plate_number' => $identity['license_plate_number']]
            );
        }

        if ($pruneMissing) {
            $vehicle->vehicleIdentities()
                ->where('provider_id', $vehicle->provider_id)
                ->whereNotIn('vin_number', $vins)
                ->delete();
        }
    }
    private function syncTranslations(Vehicle $vehicle, array $rows): void
    {
        foreach ($rows as $row) {
            if (! isset($row['locale'], $row['key'])) {
                continue;
            }

            $vehicle->translations()->updateOrCreate(
                ['locale' => $row['locale'], 'key' => $row['key']],
                ['value' => $row['value'] ?? null]
            );
        }
    }
    private function matchVendorName(mixed $group, string $search): void
    {
        foreach (array_filter(explode(' ', $search), fn ($token) => $token !== '') as $token) {
            $group->orWhere('name', 'LIKE', '%' . $token . '%');
        }
    }
    private function applyNameSearch(mixed $query, ?string $name): void
    {
        $query->when($name, fn ($builder, $value) => $builder->where(function ($group) use ($value) {
            $keywords = explode(' ', $value);

            foreach ($keywords as $keyword) {
                $group->orWhere('name', 'LIKE', '%' . $keyword . '%')->orWhere('tag', 'LIKE', '%' . $keyword . '%');
            }

            $group->applyRelationShipSearch(
                relationships: ['translations' => 'name', 'category' => 'name', 'brand' => 'name'],
                searchParameter: $keywords
            );
        }));
    }
    private function applySeatingCapacity(mixed $group, array $ranges): void
    {
        foreach ($ranges as $range) {
            $limits = explode('-', $range);
            $group->orWhereBetween('seating_capacity', [(int) $limits[0], (int) ($limits[1] ?? $limits[0])]);
        }
    }
    private function scopeToZones(mixed $query, array $zoneIds): void
    {
        $query->when(count($zoneIds) > 0, fn ($builder) => $builder->whereHas('provider', fn ($provider) => $provider
            ->active()
            ->servingPickupZones($zoneIds)));
    }
    private function personalise(mixed $query, array $filters): mixed
    {
        return ($filters['user_id'] ?? null)
            ? $this->applyItemPersonalization($query, $filters['user_id'])
            : $query;
    }
    private function priceRange(array $filters): array
    {
        if ($column = self::PRICE_COLUMN[$filters['trip_type'] ?? null] ?? null) {
            return [
                ApiCache::remember('vehicle_price', "max_{$column}", fn () => Vehicle::max($column)),
                ApiCache::remember('vehicle_price', "min_{$column}", fn () => Vehicle::where($column, '>', '0')->min($column)),
            ];
        }

        $providerId = $filters['provider_id'] ?? null;
        $maxes = [];
        $mins = [];

        foreach (['dis' => 'distance_price', 'hour' => 'hourly_price', 'day' => 'day_wise_price'] as $slug => $column) {
            $maxes[] = ApiCache::remember(
                'vehicle_price',
                "{$slug}_max_{$providerId}",
                fn () => Vehicle::when($providerId, fn ($query) => $query->where('provider_id', $providerId))->max($column)
            );
            $mins[] = ApiCache::remember(
                'vehicle_price',
                "{$slug}_min_{$providerId}",
                fn () => Vehicle::when($providerId, fn ($query) => $query->where('provider_id', $providerId))->where($column, '>', '0')->min($column)
            );
        }

        $mins = array_filter($mins, fn ($value) => ! is_null($value));

        return [max($maxes), $mins ? min($mins) : 0];
    }
}
