<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Vendor\Vehicle\TaxonomyListRequest;
use Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleTaxonomyResource;
use Modules\Rental\Services\Vehicle\VehicleBrandService;

class BrandController extends BaseApiController
{
    public function __construct(
        private readonly VehicleBrandService $vehicleBrandService
    ) {
    }

    public function index(TaxonomyListRequest $request): JsonResponse
    {
        $brands = $this->vehicleBrandService->getList([], ['per_page' => $request->perPage(), 'page' => $request->page()]);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => VehicleTaxonomyResource::collection($brands),
            'pagination' => $this->paginateFormatter($brands),
        ]);
    }
}
