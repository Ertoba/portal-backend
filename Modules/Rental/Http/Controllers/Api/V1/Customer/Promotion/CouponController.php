<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Promotion;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\CouponResource;
use App\Services\Marketing\CouponService;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Customer\Promotion\CouponApplyRequest;
use Modules\Rental\Services\Trip\TripService;
use App\Traits\Api\ApiRequestContextTrait;

class CouponController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;
    use ProCustomerSubscriptionTrait;

    private const MODULE_TYPE = 'rental';

    private const COUPON_ERROR_CODE = 'coupon';

    private const TYPE_FREE_DELIVERY = 'free_delivery';

    public function __construct(
        private readonly CouponService $couponService,
        private readonly TripService $tripService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        return $this->cachedJson('api.rental_coupons', $request, $this->filters($request), function () use ($request) {
            $coupons = $this->couponService->getCustomerList(
                filters: $this->filters($request),
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return $this->responseFormatter(config('response.default_200'), [
                'data' => CouponResource::collection($coupons),
                'pagination' => $this->paginateFormatter($coupons),
            ]);
        });
    }

    public function apply(CouponApplyRequest $request): JsonResponse
    {
        $coupon = $this->couponService->findActiveByCode($request->input('code'), self::MODULE_TYPE);

        if (! $coupon) {
            return $this->couponError(config('response.default_404'), translate('No data found'));
        }

        if ($coupon->coupon_type === self::TYPE_FREE_DELIVERY) {
            return $this->couponError(config('response.forbidden_403'), translate('messages.Invalid coupon'));
        }

        return match ($this->couponService->validateForCustomer($coupon, $request->user()->id, $request->input('provider_id'))) {
            200 => $this->responseFormatter(config('response.default_200'), new CouponResource($coupon)),
            406 => $this->couponError(config('response.not_acceptable_406'), translate('messages.Coupon usage limit over')),
            407 => $this->couponError(config('response.expired_407'), translate('messages.Coupon expire')),
            408 => $this->couponError(config('response.forbidden_403'), translate('messages.You are not eligible for this coupon')),
            409 => $this->couponError(config('response.forbidden_403'), translate('messages.Coupon not valid for this zone')),
            default => $this->couponError(config('response.default_404'), translate('No data found')),
        };
    }

    private function filters(Request $request): array
    {
        $customerId = $request->user()?->id ?? $request->input('customer_id');
        $proOffer = $this->getProCustomerOffer(userId: $customerId);
        $module = config('module.current_module_data');

        return [
            'customer_id' => $customerId,
            'store_id' => $this->providerId($request),
            'zone_ids' => $this->couponZoneIds($request),
            'module_type' => self::MODULE_TYPE,
            'all_zone_service' => $module['all_zone_service'] ?? false,
            'coupon_usage' => $this->tripService->couponUsage($customerId),
            // Was hardcoded true, which made firstOrderAllowed() skip its check entirely and
            // showed every first_order coupon to every customer however many trips they had
            // already taken. The key still has to be present -- omitting it falls through to
            // OrderService::hasNoPastOrders(), which reads a table rental never writes to.
            'first_order_eligible' => $this->tripService->hasNoPastTrips($customerId),
            'pro_coupon_eligible' => ($proOffer['status'] ?? false)
                && (($proOffer['benefit']['type'] ?? null) === 'coupon'),
        ];
    }

    private function providerId(Request $request): mixed
    {
        return is_scalar($request->input('provider_id')) ? $request->input('provider_id') : null;
    }

    private function couponZoneIds(Request $request): array
    {
        $zone = $request->input('zone_id') ?? $request->header('zoneId');

        if (is_array($zone)) {
            return $zone;
        }

        $decoded = json_decode((string) $zone, true);

        return is_array($decoded)
            ? $decoded
            : array_values(array_filter([$zone], fn ($value) => $value !== null && $value !== ''));
    }

    private function couponError(array $config, string $message): JsonResponse
    {
        return $this->responseFormatter($config, errors: [
            ['code' => self::COUPON_ERROR_CODE, 'message' => $message],
        ]);
    }
}
