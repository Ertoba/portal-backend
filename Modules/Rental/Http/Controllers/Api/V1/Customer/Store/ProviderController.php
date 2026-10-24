<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Store;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Store\StoreDetailResource;
use App\Services\Store\StoreService;
use App\Traits\Store\StorePayloadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Resources\Customer\Store\ProviderListResource;
use App\Traits\Api\ApiRequestContextTrait;

class ProviderController extends BaseApiController
{
    use ApiRequestContextTrait;
    use StorePayloadTrait;

    public function __construct(
        private readonly StoreService $storeService
    ) {}

    public function show(Request $request, mixed $provider): JsonResponse
    {
        $store = $this->storeService->findProviderDetail($provider);

        if (! $store) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'provider_not_found');
        }

        if ($customerId = auth('api')->id()) {
            $this->storeService->recordVisit($store->id, $customerId);
        }

        return $this->responseFormatter(config('response.default_200'), new StoreDetailResource($this->loadStoreRelations($store)));
    }

    public function latest(Request $request): JsonResponse
    {
        return $this->providerList($this->storeService->getProviderLatestList($this->filters($request), $this->pagination($request)));
    }

    public function popular(Request $request): JsonResponse
    {
        return $this->providerList($this->storeService->getProviderPopularList($this->filters($request), $this->pagination($request)));
    }

    public function verified(Request $request): JsonResponse
    {
        if (! Helpers::get_business_settings('verified_seller_badge')) {
            return $this->providerList(['total_size' => 0, 'stores' => [], 'paginator' => $this->emptyPaginator($this->perPage($request), $this->page($request))]);
        }

        return $this->providerList($this->storeService->getProviderVerifiedList($this->filters($request), $this->pagination($request)));
    }

    private function filters(Request $request): array
    {
        $this->applyZoneIds($request);

        return [
            'zone_id' => $request->header('zoneId'),
            'longitude' => $request->header('longitude'),
            'latitude' => $request->header('latitude'),
            'user_id' => auth('api')->id(),
        ];
    }

    private function pagination(Request $request): array
    {
        return ['per_page' => $this->perPage($request), 'page' => $this->page($request)];
    }

    private function providerList(array $payload): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => ProviderListResource::renderList($payload['stores'] ?? []),
            'pagination' => $this->paginateFormatter($payload['paginator']),
        ]);
    }
}
