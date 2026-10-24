<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreService;
use App\Services\Zone\ZoneService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Customer\PersonalizationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Customer\Vehicle\ProviderVehicleRequest;
use Modules\Rental\Http\Requests\Customer\Vehicle\VehicleSearchRequest;
use Modules\Rental\Http\Resources\Customer\Vehicle\VehicleDetailResource;
use Modules\Rental\Http\Resources\Customer\Vehicle\VehicleResource;
use Modules\Rental\Services\Vehicle\VehicleService;

class VehicleController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait, PersonalizationTrait;

    public function __construct(
        private readonly VehicleService $vehicleService,
        private readonly StoreService $storeService,
        private readonly ZoneService $zoneService
    ) {
    }

    public function topRated(VehicleSearchRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        return $this->cachedJson('api.rental_top_rated', $request, $request->filters(), function () use ($request) {
            $vehicles = $this->vehicleService->getTopRatedList($request->filters(), $this->pageParams($request));

            return $this->pagedResponse($vehicles, VehicleResource::class);
        });
    }

    public function search(VehicleSearchRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        [$latitude, $longitude] = $request->pickupLocation();
        $zones = $latitude && $longitude ? $this->zoneService->findIdsByCoordinates($latitude, $longitude) : [];

        if ($latitude && $longitude && ! $zones) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('messages.Out of pick up zone'),
                'zone'
            );
        }

        return $this->vehicleListResponse(
            $this->vehicleService->getSearchList(array_merge($request->filters(), ['pickup_zones' => $zones]), $this->pageParams($request))
        );
    }

    public function suggestion(VehicleSearchRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        return $this->responseFormatter(
            config('response.default_200'),
            $this->vehicleService->getSuggestionList($request->filters())
        );
    }

    public function providerVehicles(ProviderVehicleRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $provider = $this->storeService->findByIdOrSlug($request->input('provider_id'));

        if (! $provider) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'provider');
        }

        return $this->vehicleListResponse(
            $this->vehicleService->getSearchList(array_merge($request->filters(), ['provider_id' => $provider->id]), $this->pageParams($request))
        );
    }

    public function show(Request $request, mixed $vehicle): JsonResponse
    {
        $found = $this->vehicleService->findDetail($vehicle);

        if (! $found) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'vehicle');
        }

        if ($userId = auth('api')->id()) {
            $this->recordVehicleAction((int) $userId, (int) $found->id, 'item_view', $this->currentModuleId());
        }

        return $this->responseFormatter(
            config('response.default_200'),
            new VehicleDetailResource($found)
        );
    }

    private function vehicleListResponse(array $result): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => VehicleResource::collection($result['vehicles']),
            'pagination' => $this->paginateFormatter($result['vehicles']),
            'max_price' => $result['max_price'],
            'min_price' => $result['min_price'],
        ]);
    }


}
