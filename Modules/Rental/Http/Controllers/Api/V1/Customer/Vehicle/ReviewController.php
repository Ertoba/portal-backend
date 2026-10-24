<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Customer\Vehicle\VehicleReviewListRequest;
use Modules\Rental\Http\Requests\Customer\Vehicle\VehicleReviewRequest;
use Modules\Rental\Http\Resources\Customer\Vehicle\VehicleReviewResource;
use Modules\Rental\Services\Vehicle\VehicleReviewService;

class ReviewController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VehicleReviewService $vehicleReviewService
    ) {
    }

    public function index(VehicleReviewListRequest $request): JsonResponse
    {
        $reviews = $this->vehicleReviewService->getVehicleList($request->filters(), $this->pageParams($request));

        return $this->pagedResponse($reviews, VehicleReviewResource::class);
    }

    public function store(VehicleReviewRequest $request): JsonResponse
    {
        if ($this->vehicleReviewService->alreadyReviewed($request->reviewKeys())) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Already submitted'), 'review');
        }

        $this->vehicleReviewService->submit($request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }
}
