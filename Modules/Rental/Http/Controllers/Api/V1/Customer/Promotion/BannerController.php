<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Promotion;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Marketing\BannerService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Resources\Customer\Promotion\BannerResource;
use Modules\Rental\Http\Resources\Customer\Promotion\StoreBannerResource;

class BannerController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    private const MODULE_TYPE = 'rental';

    public function __construct(
        private readonly BannerService $bannerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        return $this->cachedJson('api.rental_banners', $request, $this->filters($request), fn () => $this->responseFormatter(config('response.default_200'), [
            'banners' => BannerResource::collection(
                $this->bannerService->getModuleTypeBanners($this->filters($request))
            ),
        ]));
    }

    public function listForStore(Request $request, mixed $storeId): JsonResponse
    {
        $this->applyZoneIds($request);

        $banners = $this->bannerService->getStoreBannerList(
            $storeId,
            array_merge($this->filters($request), ['featured_first' => true, 'module_id' => null]),
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => StoreBannerResource::collection($banners),
            'pagination' => $this->paginateFormatter($banners),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'zone_id' => $request->header('zoneId'),
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'module_type' => self::MODULE_TYPE,
            'featured' => $request->query('featured'),
            'locale' => app()->getLocale(),
        ];
    }
}
