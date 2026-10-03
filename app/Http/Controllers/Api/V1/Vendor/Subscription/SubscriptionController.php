<?php

namespace App\Http\Controllers\Api\V1\Vendor\Subscription;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Subscription\BusinessPlanRequest;
use App\Http\Requests\Vendor\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Vendor\Subscription\ProductLimitRequest;
use App\Models\Store;
use App\Services\Payment\StoreSubscriptionService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly StoreSubscriptionService $storeSubscriptionService) {}

    public function businessPlan(BusinessPlanRequest $request): JsonResponse
    {
        $store = Store::find($request->input('store_id'));

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $result = $this->storeSubscriptionService->choosePlan($store, [
            'business_plan' => $request->input('business_plan'),
            'package_id' => $request->input('package_id'),
            'payment_gateway' => $request->input('payment_gateway'),
            'payment_platform' => $request->input('payment_platform') ?? 'web',
            'callback' => $request->has('callback') ? $request->input('callback') : session('callback'),
            'type' => $request->input('type'),
        ]);

        if ($result['unresolved'] ?? false) {
            return $this->responseFormatter(config('response.default_404'));
        }

        if ($result['insufficient_balance'] ?? false) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('messages.Insufficient wallet balance'),
                'wallet'
            );
        }

        return $this->responseFormatter(config('response.default_200'), $result);
    }

    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $store = $this->ownedStore($request);

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->storeSubscriptionService->cancel([
            'subscription_id' => $request->input('subscription_id'),
            'store_id' => $store->id,
        ]);

        $this->storeSubscriptionService->notifyCancellation($store);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function checkProductLimits(ProductLimitRequest $request): JsonResponse
    {
        $store = $this->ownedStore($request);

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(
            config('response.default_200'),
            $this->storeSubscriptionService->productLimitSummary([
                'store_id' => $store->id,
                'package_id' => $request->input('package_id'),
            ])
        );
    }

    private function ownedStore(Request $request): mixed
    {
        $store = $this->vendorStore($request);

        return $store && (string) $store->id === (string) $request->input('store_id') ? $store : null;
    }
}
