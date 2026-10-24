<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Profile;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Store\StoreDetailResource;
use App\Http\Resources\Vendor\Profile\ProfileResource;
use App\Services\Store\StoreService;
use App\Services\Vendor\VendorService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Store\StorePayloadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Vendor\Profile\ProfileUpdateRequest;
use Modules\Rental\Services\Trip\TripService;

class ProfileController extends BaseApiController
{
    use ApiRequestContextTrait;
    use StorePayloadTrait;

    public function __construct(
        private readonly VendorService $vendorService,
        private readonly StoreService $storeService,
        private readonly TripService $tripService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = $request->input('vendor')->loadMissing('storage');
        $store = $this->vendorStore($request);
        $detailedStore = $this->storeService->findForVendorProfile($store->id);

        return $this->responseFormatter(config('response.default_200'), new ProfileResource($vendor, [
            'stats' => $this->tripService->providerStats($store->id) + $this->storeService->providerEarningSummary($store),
            'store' => (new StoreDetailResource($this->loadStoreRelations($store), $this->storeExtras($store)))->resolve(),
            'translations' => $detailedStore?->translations,
            'has_subscription_transactions' => $this->vendorService->hasSubscriptionTransactions($store->id),
            'subscription' => $detailedStore?->store_sub_update_application,
            'subscription_other_data' => $this->vendorService->subscriptionSummary($detailedStore),
            'out_of_stock_count' => $this->vendorService->outOfStockCount($detailedStore),
        ] + $this->employeeContext($request)));
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $this->vendorService->updateProfile($request->input('vendor'), $request->payload());

        return $this->responseFormatter(
            ['message' => translate('Updated successfully')] + config('response.default_update_200')
        );
    }

    private function storeExtras(mixed $store): array
    {
        $store->setAttribute('pickup_zone_id', json_decode($store->getRawOriginal('pickup_zone_id'), true));

        return [
            'discount' => Helpers::get_store_discount($store),
            'schedules' => $store->schedules()->get(),
            'module' => $store->module,
        ];
    }

    private function employeeContext(Request $request): array
    {
        $employee = $request->input('vendor_employee');

        if (! $employee) {
            return [];
        }

        return [
            'roles' => $employee->role ? json_decode($employee->role->modules) : [],
            'employee_info' => json_decode($employee),
        ];
    }
}
