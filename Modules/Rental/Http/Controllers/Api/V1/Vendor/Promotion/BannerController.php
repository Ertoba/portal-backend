<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\BannerAddRequest;
use App\Http\Resources\Vendor\Promotion\BannerResource;
use App\Services\Marketing\BannerService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Requests\Vendor\Promotion\BannerListRequest;
use Modules\Rental\Http\Requests\Vendor\Promotion\BannerUpdateRequest;
use Throwable;

class BannerController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly BannerService $bannerService
    ) {
    }

    public function index(BannerListRequest $request): JsonResponse
    {
        $banners = $this->bannerService->getPaginatedList(
            filters: $request->filters(),
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => BannerResource::collection($banners),
            'pagination' => $this->paginateFormatter($banners),
        ]);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $banner = $this->bannerService->find($id, filters: $this->storeFilters($request));

        return $banner
            ? $this->responseFormatter(config('response.default_200'), new BannerResource($banner))
            : $this->bannerNotFound();
    }

    public function store(BannerAddRequest $request): JsonResponse
    {
        try {
            $banner = $this->bannerService->create($request->payload());
        } catch (Throwable) {
            return $this->writeFailed(translate('messages.Failed to create banner.'));
        }

        return $this->responseFormatter(
            ['message' => translate('Added successfully')] + config('response.default_store_201'),
            new BannerResource($banner)
        );
    }

    public function update(BannerUpdateRequest $request, mixed $id): JsonResponse
    {
        try {
            $banner = $this->bannerService->update($id, $request->payload());
        } catch (Throwable) {
            return $this->writeFailed(translate('messages.Failed to update banner.'));
        }

        return $banner
            ? $this->responseFormatter(
                ['message' => translate('Updated successfully')] + config('response.default_update_200'),
                new BannerResource($banner)
            )
            : $this->bannerNotFound();
    }

    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        $banner = $this->bannerService->toggleStatus($id, $this->storeFilters($request));

        return $banner
            ? $this->responseFormatter(
                ['message' => translate('messages.Banner status updated.')] + config('response.default_update_200'),
                new BannerResource($banner)
            )
            : $this->bannerNotFound();
    }

    public function updateFeatured(Request $request, mixed $id): JsonResponse
    {
        $banner = $this->bannerService->toggleFeatured($id, $this->storeFilters($request));

        return $banner
            ? $this->responseFormatter(
                ['message' => translate('messages.Banner featured updated.')] + config('response.default_update_200'),
                new BannerResource($banner)
            )
            : $this->bannerNotFound();
    }

    public function destroy(Request $request, mixed $id): JsonResponse
    {
        return $this->bannerService->delete($id, $this->storeFilters($request))
            ? $this->responseFormatter(['message' => translate('Deleted successfully')] + config('response.default_delete_200'))
            : $this->bannerNotFound();
    }

    private function storeFilters(Request $request): array
    {
        return ['store_id' => $this->vendorStoreId($request)];
    }

    private function writeFailed(string $message): JsonResponse
    {
        return $this->responseFormatter(
            ['message' => $message] + config('response.bad_request_400'),
            errors: [['code' => 'banner', 'message' => $message]]
        );
    }

    private function bannerNotFound(): JsonResponse
    {
        return $this->responseFormatter(
            ['message' => translate('No data found')] + config('response.default_404'),
            errors: [['code' => 'not_found', 'message' => translate('No data found')]]
        );
    }
}
