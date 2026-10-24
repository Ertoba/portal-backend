<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Customer\Vehicle\TaxonomyListRequest;
use Modules\Rental\Http\Resources\Customer\Vehicle\VehicleTaxonomyResource;
use Modules\Rental\Services\Vehicle\VehicleCategoryService;

class CategoryController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VehicleCategoryService $vehicleCategoryService
    ) {
    }

    public function index(TaxonomyListRequest $request): JsonResponse
    {
        $categorys = $this->vehicleCategoryService->getList($request->filters(), $this->pageParams($request));

        return $this->pagedResponse($categorys, VehicleTaxonomyResource::class);
    }
}
