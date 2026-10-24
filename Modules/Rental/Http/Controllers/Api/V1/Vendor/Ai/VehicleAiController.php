<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Ai;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AI\app\Core\AiModule;
use Modules\Rental\Http\Requests\Vendor\Ai\ImageAnalysisRequest;
use Modules\Rental\Http\Requests\Vendor\Ai\OtherDataRequest;
use Modules\Rental\Http\Requests\Vendor\Ai\TitleAndDescriptionRequest;
use Modules\Rental\Http\Requests\Vendor\Ai\TitleSuggestionRequest;
use Modules\Rental\Services\AI\VehicleAiService;

class VehicleAiController extends BaseApiController
{
    use ApiRequestContextTrait;

    private const KIND_SECTION = 'section';

    private const KIND_IMAGE = 'image';

    public function __construct(
        private readonly VehicleAiService $vehicleAiService,
        private readonly StoreService $storeService
    ) {
    }

    public function titleAndDescription(TitleAndDescriptionRequest $request): JsonResponse
    {
        return $this->generate($request, self::KIND_SECTION, 2, function () use ($request) {
            $lang = $this->lang($request);

            return [
                'title' => $this->vehicleAiService->title($request->input('name'), $lang),
                'description' => $this->vehicleAiService->description($request->input('name'), $lang),
            ];
        });
    }

    public function otherData(OtherDataRequest $request): JsonResponse
    {
        return $this->generate($request, self::KIND_SECTION, 3, function () use ($request) {
            $lang = $this->lang($request);
            $name = $request->input('name');
            $description = (string) $request->input('description', '');

            return array_merge(
                $this->vehicleAiService->vehicleInfo($name, $description, $lang) ?? [],
                $this->vehicleAiService->pricing($name, $description, $lang) ?? [],
                ['tags' => $this->vehicleAiService->tags($name, $description, $lang)],
                $this->vehicleAiService->seo($name, $description, $lang) ?? []
            );
        });
    }

    public function titleSuggestions(TitleSuggestionRequest $request): JsonResponse
    {
        return $this->generate($request, self::KIND_SECTION, 1, fn () => [
            'titles' => $this->vehicleAiService->titleSuggestions($request->keywords(), $this->lang($request)),
        ]);
    }

    public function imageAnalysis(ImageAnalysisRequest $request): JsonResponse
    {
        return $this->generate($request, self::KIND_IMAGE, 1, function () use ($request) {
            $data = $this->vehicleAiService->imageAnalysis($request->imageDataUri(), $this->lang($request)) ?? [];

            return ['title' => $data['name'] ?? '', 'description' => $data['description'] ?? ''];
        });
    }

    private function generate(Request $request, string $kind, int $cost, callable $work): JsonResponse
    {
        if ($this->demoLimitReached()) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('Demo mode allows limited uses of this feature, then it is disabled.') . ' ' . translate('Usage limit') . ': 10',
                'demo_limit'
            );
        }

        if (! AiModule::isOpenAiConfigured()) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('AI assistant is not configured'),
                'ai'
            );
        }

        $allowance = $this->storeService->aiUsageAllowance($this->vendorStoreId($request), $kind);

        if (! $allowance['allowed']) {
            return $this->errorResponse(config('response.forbidden_403'), $this->limitMessage($kind), 'ai_limit');
        }

        try {
            $content = $work();
        } catch (\Throwable $exception) {
            Log::error('Vehicle AI (API) generation failed: ' . $exception->getMessage());

            return $this->errorResponse(
                config('response.unprocessable_entity_422'),
                translate('AI failed to generate a result please try again'),
                'ai'
            );
        }

        $this->storeService->recordAiUsage($allowance['config'], $kind, $cost);

        return $this->responseFormatter(config('response.default_200'), $content);
    }

    private function limitMessage(string $kind): string
    {
        return $kind === self::KIND_IMAGE
            ? translate('You have reached the limit of AI usage via image.')
            : translate('You have reached the limit of AI usage.');
    }

    private function lang(Request $request): string
    {
        return $request->input('langCode') ?: (Helpers::system_default_language() ?: 'en');
    }
}
