<?php

namespace Modules\Rental\Services\Vehicle;

use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Rental\Entities\VehicleBrand;
use Modules\Rental\Traits\VehicleTaxonomyTrait;

class VehicleBrandService extends BaseService
{
    use VehicleTaxonomyTrait;

    private const POPULAR_LIMIT = 10;

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->taxonomyList(VehicleBrand::query(), $filters, $paginate);
    }

    public function getPopularList(): Collection
    {
        return VehicleBrand::withStorage()
            ->where('status', 1)
            ->select(['id', 'name', 'image'])
            ->withSum('vehicles', 'total_trip')
            ->orderBy('vehicles_sum_total_trip', 'desc')
            ->take(self::POPULAR_LIMIT)
            ->get();
    }

    public function getActiveNames(): mixed
    {
        return VehicleBrand::ofStatus(1)->pluck('name', 'id');
    }

}
