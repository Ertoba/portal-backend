<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Vendor\Vehicle\TaxonomyListRequest;
use Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleTaxonomyResource;
use Modules\Rental\Services\Vehicle\VehicleCategoryService;

class CategoryController extends BaseApiController
{
    public function __construct(
        private readonly VehicleCategoryService $vehicleCategoryService
    ) {
    }

    public function index(TaxonomyListRequest $request): JsonResponse
    {
        $categories = $this->vehicleCategoryService->getList([], ['per_page' => $request->perPage(), 'page' => $request->page()]);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => VehicleTaxonomyResource::collection($categories),
            'pagination' => $this->paginateFormatter($categories),
        ]);
    }
}
