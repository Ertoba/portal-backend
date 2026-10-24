<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Vendor\Vehicle\DriverListRequest;
use Modules\Rental\Http\Requests\Vendor\Vehicle\DriverStoreRequest;
use Modules\Rental\Http\Requests\Vendor\Vehicle\DriverUpdateRequest;
use Modules\Rental\Http\Resources\Vendor\Vehicle\DriverDetailResource;
use Modules\Rental\Http\Resources\Vendor\Vehicle\DriverResource;
use Modules\Rental\Services\Vehicle\VehicleDriverService;

class DriverController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VehicleDriverService $vehicleDriverService
    ) {
    }

    public function index(DriverListRequest $request): JsonResponse
    {
        $drivers = $this->vehicleDriverService->getList($request->filters(), $this->pageParams($request));

        return $this->pagedResponse($drivers, DriverResource::class);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $driver = $this->vehicleDriverService->find($id, $this->vendorStoreId($request));

        if (! $driver) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'driver');
        }

        return $this->responseFormatter(config('response.default_200'), new DriverDetailResource($driver));
    }

    public function store(DriverStoreRequest $request): JsonResponse
    {
        $this->vehicleDriverService->create($request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function update(DriverUpdateRequest $request, mixed $id): JsonResponse
    {
        $driver = $this->vehicleDriverService->findForProvider($id, $this->vendorStoreId($request));

        if (! $driver) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'driver');
        }

        $this->vehicleDriverService->update($driver, $request->payload());

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        $driver = $this->vehicleDriverService->findForProvider($id, $this->vendorStoreId($request));

        if (! $driver) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'driver');
        }

        $this->vehicleDriverService->updateStatus($driver);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function destroy(Request $request, mixed $id): JsonResponse
    {
        $driver = $this->vehicleDriverService->findForProvider($id, $this->vendorStoreId($request));

        if (! $driver) {
            return $this->errorResponse(config('response.default_404'), translate('messages.Failed to delete driver.'), 'driver');
        }

        $this->vehicleDriverService->delete($driver);

        return $this->responseFormatter(config('response.default_delete_200'));
    }
}
