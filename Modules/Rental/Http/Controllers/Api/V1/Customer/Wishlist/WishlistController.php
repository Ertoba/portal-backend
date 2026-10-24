<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Wishlist;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Store\StoreDetailResource;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Store\StorePayloadTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Customer\Wishlist\WishlistListRequest;
use Modules\Rental\Http\Requests\Customer\Wishlist\WishlistTargetRequest;
use Modules\Rental\Http\Resources\Customer\Vehicle\VehicleResource;
use Modules\Rental\Services\Wishlist\RentalWishlistService;

class WishlistController extends BaseApiController
{
    use ApiRequestContextTrait;
    use StorePayloadTrait;

    public function __construct(
        private readonly RentalWishlistService $rentalWishlistService
    ) {}

    public function index(WishlistListRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $wishlists = $this->rentalWishlistService->getList($request->filters(), $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'providers' => $this->loadStoreRelationsForMany(
                new EloquentCollection($wishlists->getCollection()->pluck('provider')->filter()->values()->all())
            )->map(fn ($provider) => new StoreDetailResource($provider)),
            'vehicles' => VehicleResource::collection($wishlists->getCollection()->pluck('vehicle')->filter()->values()),
            'pagination' => $this->paginateFormatter($wishlists),
        ]);
    }

    public function store(WishlistTargetRequest $request): JsonResponse
    {
        if ($request->targetCount() > 1) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('Cannot add both vehicle and provider at same time'),
                'data'
            );
        }

        if ($this->rentalWishlistService->exists($request->user()->id, $request->target())) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('messages.Already in wishlist'),
                'wishlist'
            );
        }

        $this->rentalWishlistService->create($request->target() + [
            'user_id' => $request->user()->id,
            'module_id' => $this->currentModuleId(),
        ]);

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function destroy(WishlistTargetRequest $request): JsonResponse
    {
        $wishlist = $this->rentalWishlistService->findOwned($request->user()->id, $request->target());

        if (! $wishlist) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'wishlist');
        }

        $this->rentalWishlistService->delete($wishlist);

        $label = $request->targetLabel('removed from');

        return $this->responseFormatter([
            'identical_code' => 'default_delete_200',
            'message' => $label,
            'http_response_code' => 200,
        ], [
            'message' => translate($label),
        ]);
    }
}
