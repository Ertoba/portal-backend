<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Promotion\CouponResource;
use App\Services\Marketing\CouponService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Vendor\Promotion\CouponListRequest;
use Modules\Rental\Http\Requests\Vendor\Promotion\CouponStoreRequest;
use Modules\Rental\Http\Requests\Vendor\Promotion\CouponUpdateRequest;

class CouponController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly CouponService $couponService
    ) {
    }

    public function index(CouponListRequest $request): JsonResponse
    {
        $paginate = ['per_page' => $request->perPage(), 'page' => $request->page()];

        $coupons = $request->filled('search')
            ? $this->couponService->getSearchList($request->filters(), $paginate)
            : $this->couponService->getList($request->filters(), $paginate);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CouponResource::collection($coupons),
            'pagination' => $this->paginateFormatter($coupons),
        ]);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $coupon = $this->couponService->findWithTranslations($id, $this->vendorStoreId($request));

        return $coupon
            ? $this->responseFormatter(config('response.default_200'), new CouponResource($coupon))
            : $this->couponNotFound();
    }

    public function store(CouponStoreRequest $request): JsonResponse
    {
        $this->couponService->create(array_merge($request->payload(), [
            'store_id' => $this->vendorStoreId($request),
            'module_id' => $this->vendorStore($request)?->module_id,
            'data' => null,
        ]));

        return $this->responseFormatter(
            ['message' => translate('Added successfully')] + config('response.default_store_201')
        );
    }

    public function update(CouponUpdateRequest $request, mixed $id): JsonResponse
    {
        $coupon = $this->couponService->update($id, $request->payload(), $this->vendorStoreId($request));

        return $coupon
            ? $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'))
            : $this->couponNotFound();
    }

    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        $coupon = $this->couponService->toggleStatus($id, $this->vendorStoreId($request));

        return $coupon
            ? $this->responseFormatter(['message' => translate('messages.Coupon status updated')] + config('response.default_update_200'))
            : $this->couponNotFound();
    }

    public function destroy(Request $request, mixed $id): JsonResponse
    {
        return $this->couponService->delete($id, $this->vendorStoreId($request))
            ? $this->responseFormatter(['message' => translate('Deleted successfully')] + config('response.default_delete_200'))
            : $this->couponNotFound();
    }

    private function couponNotFound(): JsonResponse
    {
        return $this->responseFormatter(
            ['message' => translate('No data found')] + config('response.default_404'),
            errors: [['code' => 'not_found', 'message' => translate('No data found')]]
        );
    }
}
