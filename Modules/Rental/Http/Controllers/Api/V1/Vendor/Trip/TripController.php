<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Trip;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Zone\ZoneService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Vendor\Trip\AssignDriverRequest;
use Modules\Rental\Http\Requests\Vendor\Trip\AssignVehicleRequest;
use Modules\Rental\Http\Requests\Vendor\Trip\TripEditRequest;
use Modules\Rental\Http\Requests\Vendor\Trip\TripListRequest;
use Modules\Rental\Http\Requests\Vendor\Trip\TripPaymentRequest;
use Modules\Rental\Http\Requests\Vendor\Trip\TripStatusRequest;
use Modules\Rental\Http\Requests\Vendor\Trip\TripTargetRequest;
use Modules\Rental\Http\Resources\Vendor\Trip\TripDetailResource;
use Modules\Rental\Http\Resources\Vendor\Trip\TripResource;
use Modules\Rental\Http\Resources\Vendor\Trip\TripVehicleDetailResource;
use Modules\Rental\Services\Trip\TripService;
use Modules\Rental\Traits\RentalPushNotification;

class TripController extends BaseApiController
{
    use ApiRequestContextTrait, RentalPushNotification;

    public function __construct(
        private readonly TripService $tripService,
        private readonly ZoneService $zoneService
    ) {
    }

    public function index(TripListRequest $request, string $tripStatus): JsonResponse
    {
        $trips = $this->tripService->getProviderList($request->filters($tripStatus), $this->pageParams($request));

        return $this->pagedResponse($trips, TripResource::class);
    }

    public function show(TripTargetRequest $request): JsonResponse
    {
        $trip = $this->tripService->findProviderDetail($request->tripId(), $request->providerId());

        return $trip
            ? $this->responseFormatter(config('response.default_200'), new TripDetailResource($trip))
            : $this->notFound(translate('No data found'));
    }

    public function updateStatus(TripStatusRequest $request): JsonResponse
    {
        $trip = $this->tripService->findForProvider(
            $request->tripId(),
            $request->providerId(),
            ['trip_details.vehicle', 'trip_transaction:id,trip_id']
        );

        if (! $trip) {
            return $this->notFound(translate('No data found'));
        }

        if ($this->tripService->isClosed($trip)) {
            return $this->forbidden(translate('You cannot change this trip status'), 'trip_status');
        }

        if (in_array($request->input('trip_status'), ['ongoing', 'completed'], true)
            && ! $this->tripService->hasAssignedVehicle($trip)
            && ! $this->tripService->hasDeletedVehicle($trip)) {
            return $this->forbidden(translate('At first assign a vehicle'), 'trip_status');
        }

        if (! $this->tripService->updateStatus($trip, $request->payload())) {
            return $this->forbidden(translate('Failed to create transaction'), 'transaction');
        }

        $this->sendTripNotificationCustomer($trip);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updatePaymentStatus(TripPaymentRequest $request): JsonResponse
    {
        $trip = $this->tripService->findForProvider(
            $request->tripId(),
            $request->providerId(),
            ['trip_transaction:id,trip_id']
        );

        if (! $trip) {
            return $this->notFound(translate('No data found'));
        }

        if (! $this->tripService->updatePaymentStatus($trip, $request->payload())) {
            return $this->forbidden(translate('Failed to create transaction'), 'transaction');
        }

        $this->sendTripPaymentNotificationCustomer($trip);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function assignDriver(AssignDriverRequest $request): JsonResponse
    {
        $trip = $this->tripService->findForProvider($request->tripId(), $request->providerId());

        if (! $trip) {
            return $this->notFound(translate('No data found'));
        }

        $rows = $this->tripService->assignDrivers($trip, $request->driverMap());

        return $rows === null
            ? $this->notFound(translate('No data found'))
            : $this->responseFormatter(config('response.default_update_200'), TripVehicleDetailResource::collection($rows));
    }

    public function assignVehicle(AssignVehicleRequest $request): JsonResponse
    {
        $trip = $this->tripService->findForProvider($request->tripId(), $request->providerId());

        if (! $trip) {
            return $this->notFound(translate('No data found'));
        }

        if (! $this->tripService->providerOwnsVehicle($request->input('vehicle_id'), $request->providerId())) {
            return $this->notFound(translate('No data found'));
        }

        $rows = $this->tripService->assignVehicles($trip, $request->payload());

        return $this->responseFormatter(config('response.default_update_200'), TripVehicleDetailResource::collection($rows));
    }

    public function edit(TripEditRequest $request): JsonResponse
    {
        $trip = $this->tripService->findForProvider($request->tripId(), $request->providerId(), ['trip_details']);

        if (! $trip) {
            return $this->forbidden(translate('No data found'), 'coordinates');
        }

        if ($this->tripService->isClosed($trip)) {
            return $this->forbidden(translate('messages.You can not edit this'), 'coordinates');
        }

        $pickup = $request->pickupLocation($trip);

        if ($this->zoneService->outOfServiceArea(
            $trip->provider?->pickupZoneIds() ?? [],
            $this->zoneService->findIdsByCoordinates(data_get($pickup, 'lat'), data_get($pickup, 'lng'))
        )) {
            return $this->forbidden(translate('messages.Pickup location is out of zone'), 'coordinates');
        }

        $updated = $this->tripService->applyEdit($request, $trip, $request->payload($trip));

        return is_array($updated) && isset($updated['errors'])
            ? $this->forbidden($updated['errors'], 'trip')
            : $this->responseFormatter(config('response.default_200'), new TripDetailResource($updated));
    }

    private function notFound(string $message): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), $message, 'trip');
    }

    private function forbidden(string $message, string $code): JsonResponse
    {
        return $this->errorResponse(config('response.forbidden_403'), $message, $code);
    }
}
