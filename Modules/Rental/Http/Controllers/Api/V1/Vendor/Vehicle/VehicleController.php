<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Vendor\Vehicle\ReviewReplyRequest;
use Modules\Rental\Http\Requests\Vendor\Vehicle\VehicleListRequest;
use Modules\Rental\Http\Requests\Vendor\Vehicle\VehicleStoreRequest;
use Modules\Rental\Http\Requests\Vendor\Vehicle\VehicleUpdateRequest;
use Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleResource;
use Modules\Rental\Http\Resources\Vendor\Vehicle\VehicleReviewResource;
use Modules\Rental\Services\Vehicle\VehicleReviewService;
use Modules\Rental\Services\Vehicle\VehicleService;

class VehicleController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VehicleService $vehicleService,
        private readonly VehicleReviewService $vehicleReviewService
    ) {
    }

    public function index(VehicleListRequest $request): JsonResponse
    {
        $vehicles = $this->vehicleService->getVendorList($request->filters(), $this->pageParams($request));

        return $this->pagedResponse($vehicles, VehicleResource::class);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $vehicle = $this->vehicleService->findVendorDetail($id, $this->vendorStoreId($request));

        return $vehicle
            ? $this->responseFormatter(config('response.default_200'), new VehicleResource($vehicle))
            : $this->notFound();
    }

    public function edit(Request $request, mixed $id): JsonResponse
    {
        $vehicle = $this->vehicleService->findForEdit($id, $this->vendorStoreId($request));

        return $vehicle
            ? $this->responseFormatter(config('response.default_200'), new VehicleResource($vehicle))
            : $this->notFound();
    }

    public function store(VehicleStoreRequest $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        if ($message = $this->vehicleService->vehicleLimitMessage($store)) {
            return $this->errorResponse(config('response.forbidden_403'), $message, 'vehicle_limit');
        }

        $this->vehicleService->create($request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function update(VehicleUpdateRequest $request, mixed $id): JsonResponse
    {
        $vehicle = $this->vehicleService->findForProvider($id, $this->vendorStoreId($request));

        if (! $vehicle) {
            return $this->notFound();
        }

        $this->vehicleService->update($vehicle, $request->payload());

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        $vehicle = $this->vehicleService->findForProvider($id, $this->vendorStoreId($request));

        if (! $vehicle) {
            return $this->notFound();
        }

        if (! $vehicle->status && $this->packageBlocksActivation($request)) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('messages.Your current package doesnot allow to activate more then allocated vehicles in your package.'),
                'vehicle_limit'
            );
        }

        $this->vehicleService->updateStatus($vehicle);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateNewTag(Request $request, mixed $id): JsonResponse
    {
        $vehicle = $this->vehicleService->findForProvider($id, $this->vendorStoreId($request));

        if (! $vehicle) {
            return $this->notFound();
        }

        $this->vehicleService->toggleNewTag($vehicle);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function destroy(Request $request, mixed $id): JsonResponse
    {
        $vehicle = $this->vehicleService->findForProvider($id, $this->vendorStoreId($request));

        if (! $vehicle) {
            return $this->errorResponse(config('response.default_404'), translate('messages.Failed to delete vehicle.'), 'vehicle');
        }

        $this->vehicleService->delete($vehicle);

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    public function reviews(VehicleListRequest $request): JsonResponse
    {
        $reviews = $this->vehicleReviewService->getProviderList(
            ['provider_id' => $this->vendorStoreId($request), 'search' => $request->input('search')],
            $this->pageParams($request)
        );

        return $this->pagedResponse($reviews, VehicleReviewResource::class);
    }

    public function updateReply(ReviewReplyRequest $request): JsonResponse
    {
        $review = $this->vehicleReviewService->findReplyTarget($request->input('id'), $this->vendorStoreId($request));

        if (! $review) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'review');
        }

        $this->vehicleReviewService->updateReply($review, $request->input('reply'));

        return $this->responseFormatter(config('response.default_update_200'));
    }

    private function packageBlocksActivation(Request $request): bool
    {
        $check = $this->vendorStore($request)?->product_uploaad_check;

        return $check !== null && ! in_array($check, ['unlimited', 'commission'], true) && $check >= 0;
    }

    private function notFound(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'vehicle');
    }
}
