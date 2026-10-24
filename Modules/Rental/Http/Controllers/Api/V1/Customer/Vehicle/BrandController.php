<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Customer\Vehicle\TaxonomyListRequest;
use Modules\Rental\Http\Resources\Customer\Vehicle\VehicleTaxonomyResource;
use Modules\Rental\Services\Vehicle\VehicleBrandService;

class BrandController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VehicleBrandService $vehicleBrandService
    ) {
    }

    public function index(TaxonomyListRequest $request): JsonResponse
    {
        $brands = $this->vehicleBrandService->getList($request->filters(), $this->pageParams($request));

        return $this->pagedResponse($brands, VehicleTaxonomyResource::class);
    }

    public function popular(): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            VehicleTaxonomyResource::collection($this->vehicleBrandService->getPopularList())
        );
    }
}
