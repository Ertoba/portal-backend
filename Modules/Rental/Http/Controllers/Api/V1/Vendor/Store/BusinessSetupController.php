<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Vendor\Store\BusinessSetupUpdateRequest;

class BusinessSetupController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly StoreService $storeService
    ) {
    }

    public function update(BusinessSetupUpdateRequest $request): JsonResponse
    {
        $this->storeService->updateBusinessSetup($this->vendorStore($request), $request->payload());

        return $this->responseFormatter(config('response.default_update_200'));
    }
}
