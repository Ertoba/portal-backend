<?php

namespace Modules\Rental\Http\Controllers\Api\Provider;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\StoreConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\AI\app\Core\AiModule;
use Modules\Rental\Services\AI\VehicleAiService;

class VehicleAiController extends Controller
{
    private VehicleAiService $ai;

    public function __construct()
    {
        $this->ai = new VehicleAiService();

        if (function_exists('getEnvMode') && getEnvMode() == 'demo') {
            $ip = request()->header('x-forwarded-for');
            $cacheKey = 'restricted_ip_' . $ip;
            $hits = Cache::store('file')->get($cacheKey, 0);
            if ($hits >= 10) {
                abort(403, translate('Demo Mode Restriction: This feature can only be accessed 10 times in demo mode. Further attempts are disabled to maintain a fair demo experience.'));
            }
            Cache::store('file')->forever($cacheKey, $hits + 1);
        }
    }

    public function getTitleAndDescription(Request $request): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'langCode' => 'nullable|string|max:10',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($blocked = $this->enforceLimit($request, 'section', $config)) {
            return $blocked;
        }

        try {
            $lang = $this->resolveLang($request);
            $title = $this->ai->title($request->name, $lang);
            $description = $this->ai->description($request->name, $lang);

            $this->bumpLimit($config, 'section', 2);

            return response()->json(['title' => $title, 'description' => $description]);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    public function getOtherData(Request $request): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($blocked = $this->enforceLimit($request, 'section', $config)) {
            return $blocked;
        }

        try {
            $lang = $this->resolveLang($request);
            $desc = (string) $request->input('description', '');

            $data = array_merge(
                $this->ai->vehicleInfo($request->name, $desc, $lang) ?? [],
                $this->ai->pricing($request->name, $desc, $lang) ?? [],
                ['tags' => $this->ai->tags($request->name, $desc, $lang)],
                $this->ai->seo($request->name, $desc, $lang) ?? []
            );

            $this->bumpLimit($config, 'section', 3);

            return response()->json($data);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    public function generateTitleSuggestions(Request $request): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), ['keywords' => 'required|string|max:255']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($blocked = $this->enforceLimit($request, 'section', $config)) {
            return $blocked;
        }

        try {
            $titles = $this->ai->titleSuggestions(array_map('trim', explode(',', $request->keywords)), $this->resolveLang($request));

            $this->bumpLimit($config, 'section', 1);

            return response()->json(['titles' => $titles]);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    public function analyzeImageAutoFill(Request $request): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), ['image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($blocked = $this->enforceLimit($request, 'image', $config)) {
            return $blocked;
        }

        try {
            $file = $request->file('image');
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file->getRealPath()));

            $data = $this->ai->imageAnalysis($dataUri, $this->resolveLang($request)) ?? [];

            $this->bumpLimit($config, 'image', 1);

            return response()->json(['title' => $data['name'] ?? '', 'description' => $data['description'] ?? '']);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    private function enforceLimit(Request $request, string $kind, &$config = null): ?JsonResponse
    {
        $config = null;
        $storeId = $request->vendor?->stores?->first()?->id;
        if (! $storeId) {
            return null;
        }

        $row = StoreConfig::firstOrNew(['store_id' => $storeId]);

        if ($kind === 'image') {
            $limit = Helpers::get_business_settings('image_upload_limit_for_ai');
            $used = (int) ($row->image_wise_ai_use_count ?? 0);
            $message = translate('You have reached the limit of AI usage via Image.');
        } else {
            $limit = Helpers::get_business_settings('section_wise_ai_limit');
            $used = (int) ($row->section_wise_ai_use_count ?? 0);
            $message = translate('You have reached the limit of AI usage.');
        }

        if ($limit == 0 || $limit === null || $limit <= $used) {
            return response()->json(['errors' => [['code' => 'ai_limit', 'message' => $message]]], 403);
        }

        $config = $row;

        return null;
    }

    private function bumpLimit(?StoreConfig $config, string $kind, int $by): void
    {
        if (! $config) {
            return;
        }
        $column = $kind === 'image' ? 'image_wise_ai_use_count' : 'section_wise_ai_use_count';
        if ($config->exists) {
            $config->increment($column, $by);
        } else {
            $config->{$column} = $by;
            $config->save();
        }
    }

    private function resolveLang(Request $request): string
    {
        return $request->input('langCode') ?: (Helpers::system_default_language() ?: 'en');
    }

    private function disabledResponse(): JsonResponse
    {
        return response()->json(['errors' => [['code' => 'ai', 'message' => translate('AI assistant is not configured.')]]], 403);
    }

    private function failureResponse(\Throwable $e): JsonResponse
    {
        info('Vehicle AI (API) generation failed: ' . $e->getMessage());

        return response()->json(['errors' => [['code' => 'ai', 'message' => translate('AI failed to generate a result. Please try again.')]]], 422);
    }
}
