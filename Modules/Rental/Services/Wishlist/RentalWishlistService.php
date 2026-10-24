<?php

namespace Modules\Rental\Services\Wishlist;

use App\Services\BaseService;
use App\Traits\Customer\PersonalizationTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Rental\Entities\RentalWishlish;

class RentalWishlistService extends BaseService
{
    use PersonalizationTrait;

    private const PROVIDER_RELATIONS = ['storage', 'module.storage', 'schedules', 'store_sub', 'storeConfig'];

    private const VEHICLE_RELATIONS = ['storage', 'provider.storage', 'provider.storeConfig'];

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $paginator = RentalWishlish::where('user_id', $filters['user_id'] ?? null)
            ->with([
                'vehicle' => fn ($query) => $query->active()
                    ->withWhereHas('provider', fn ($provider) => $this->constrainProvider($provider, $filters)),
                'provider' => fn ($query) => $this->constrainProvider($query, $filters)
                    ->withOpen($filters['longitude'] ?? 0, $filters['latitude'] ?? 0),
            ])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $this->loadListRelations($paginator);
    }


    public function findOwned(mixed $userId, array $target): ?RentalWishlish
    {
        return RentalWishlish::where('user_id', $userId)
            ->when($target['vehicle_id'] ?? null, fn ($query, $id) => $query->where('vehicle_id', $id))
            ->when($target['provider_id'] ?? null, fn ($query, $id) => $query->where('provider_id', $id))
            ->first();
    }

    public function exists(mixed $userId, array $target): bool
    {
        return RentalWishlish::where('user_id', $userId)
            ->where('vehicle_id', $target['vehicle_id'] ?? null)
            ->where('provider_id', $target['provider_id'] ?? null)
            ->exists();
    }

    public function create(array $data): RentalWishlish
    {
        $wishlist = new RentalWishlish();
        $wishlist->user_id = $data['user_id'];
        $wishlist->vehicle_id = $data['vehicle_id'] ?? null;
        $wishlist->provider_id = $data['provider_id'] ?? null;
        $wishlist->save();

        $this->recordPersonalization($data);

        return $wishlist;
    }

    public function delete(RentalWishlish $wishlist): bool
    {
        return (bool) $wishlist->delete();
    }

    private function recordPersonalization(array $data): void
    {
        if ($data['vehicle_id'] ?? null) {
            $this->recordVehicleAction((int) $data['user_id'], (int) $data['vehicle_id'], 'item_wishlist', $data['module_id'] ?? null);
        }

        if ($data['provider_id'] ?? null) {
            $this->recordStoreAction((int) $data['user_id'], (int) $data['provider_id'], 'store_wishlist');
        }
    }

    private function constrainProvider(mixed $query, array $filters): mixed
    {
        return $query->active()
            ->when($filters['module_id'] ?? null, fn ($builder, $moduleId) => $builder
                ->module($moduleId)
                ->whereHas('zone.modules', fn ($zone) => $zone->where('modules.id', $moduleId)))
            ->whereHas('module', fn ($module) => $module->where('status', 1))
            ->when($filters['zone_ids'] ?? [], fn ($builder, $zoneIds) => $builder->servingPickupZones($zoneIds));
    }

    private function loadListRelations(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $rows = $paginator->getCollection();

        $providers = $rows->pluck('provider')->filter();
        if ($providers->isNotEmpty()) {
            (new EloquentCollection($providers->all()))->loadMissing(self::PROVIDER_RELATIONS);
        }

        $vehicles = $rows->pluck('vehicle')->filter();
        if ($vehicles->isNotEmpty()) {
            (new EloquentCollection($vehicles->all()))->loadMissing(self::VEHICLE_RELATIONS);
        }

        return $paginator;
    }
}
