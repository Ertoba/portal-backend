<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Customer\Store\ProviderReviewListRequest;
use Modules\Rental\Http\Resources\Customer\Store\ProviderReviewResource;
use Modules\Rental\Services\Vehicle\VehicleReviewService;

class ProviderReviewController extends BaseApiController
{
    public function __construct(
        private readonly VehicleReviewService $vehicleReviewService,
        private readonly StoreService $storeService
    ) {
    }

    public function index(ProviderReviewListRequest $request, mixed $provider): JsonResponse
    {
        $store = $this->storeService->findProviderDetail($provider);

        if (! $store) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'provider_not_found');
        }

        $reviews = $this->vehicleReviewService->getProviderList(
            array_merge($request->filters(), ['provider_id' => $store->id]),
            ['per_page' => $request->perPage(), 'page' => $request->page()],
        );
        $ratings = $this->storeService->calculateRating($store['rating']);

        return $this->responseFormatter(config('response.default_200'), [
            'provider' => [
                'name' => $store->name,
                'ratings' => $store->rating,
                'avg_rating' => $ratings['rating'],
                'rating_count' => $ratings['total'],
            ],
            'data' => ProviderReviewResource::collection($reviews),
            'pagination' => $this->paginateFormatter($reviews),
        ]);
    }
}
