<?php

namespace Modules\Rental\Services\Vehicle;

use App\Services\BaseService;
use App\Traits\Customer\PersonalizationTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Rental\Entities\VehicleCategory;
use Modules\Rental\Traits\VehicleTaxonomyTrait;

class VehicleCategoryService extends BaseService
{
    use VehicleTaxonomyTrait, PersonalizationTrait;

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $query = VehicleCategory::query();

        if ($filters['user_id'] ?? null) {
            $query = $this->applyCategoryPersonalization($query, $filters['user_id']);
        }

        return $this->taxonomyList($query, $filters, $paginate);
    }

    public function getActiveNames(): mixed
    {
        return VehicleCategory::ofStatus(1)->pluck('name', 'id');
    }

}
