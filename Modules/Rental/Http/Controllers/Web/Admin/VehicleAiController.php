<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;

use App\CentralLogics\Helpers;
use App\Models\StoreConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

    public function generateTitleSuggestions(Request $request): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), ['keywords' => 'required|string|max:255']);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if ($blocked = $this->enforceLimit($request, 'section', $config)) {
            return $blocked;
        }

        $keywords = array_map('trim', explode(',', $request->keywords));

        try {
            $titles = $this->ai->titleSuggestions($keywords, $this->resolveLang($request));
            if (empty($titles)) {
                return $this->emptyResultResponse();
            }

            $this->bumpLimit($config, 'section');

            return response()->json(['data' => ['titles' => $titles]]);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    public function analyzeImage(Request $request): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), ['image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if ($blocked = $this->enforceLimit($request, 'image', $config)) {
            return $blocked;
        }

        try {
            $file = $request->file('image');
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file->getRealPath()));

            $data = $this->ai->imageAnalysis($dataUri, $this->resolveLang($request));
            if (empty($data)) {
                return $this->emptyResultResponse();
            }

            $this->bumpLimit($config, 'image');

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    public function titleAutoFill(Request $request): JsonResponse
    {
        return $this->runTextSection($request, function ($name, $desc, $lang) {
            $title = $this->ai->title($name, $lang);

            return $title !== null ? ['title' => $title] : null;
        });
    }

    public function generateDescription(Request $request): JsonResponse
    {
        return $this->runTextSection($request, function ($name, $desc, $lang) {
            $description = $this->ai->description($name, $lang);

            return $description !== null ? ['description' => $description] : null;
        });
    }

    public function vehicleInfoAutoFill(Request $request): JsonResponse
    {
        return $this->runTextSection($request, fn ($name, $desc, $lang) => $this->ai->vehicleInfo($name, $desc, $lang));
    }

    public function pricingAutoFill(Request $request): JsonResponse
    {
        return $this->runTextSection($request, fn ($name, $desc, $lang) => $this->ai->pricing($name, $desc, $lang));
    }

    public function tagsAutoFill(Request $request): JsonResponse
    {
        return $this->runTextSection($request, function ($name, $desc, $lang) {
            $tags = $this->ai->tags($name, $desc, $lang);

            return ! empty($tags) ? ['tags' => $tags] : null;
        });
    }

    public function seoAutoFill(Request $request): JsonResponse
    {
        return $this->runTextSection($request, fn ($name, $desc, $lang) => $this->ai->seo($name, $desc, $lang));
    }

    private function runTextSection(Request $request, callable $generate): JsonResponse
    {
        if (! AiModule::isOpenAiConfigured()) {
            return $this->disabledResponse();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if ($blocked = $this->enforceLimit($request, 'section', $config)) {
            return $blocked;
        }

        try {
            $data = $generate($request->name, (string) $request->input('description', ''), $this->resolveLang($request));
            if (empty($data)) {
                return $this->emptyResultResponse();
            }

            $this->bumpLimit($config, 'section');

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            return $this->failureResponse($e);
        }
    }

    private function enforceLimit(Request $request, string $kind, &$config = null): ?JsonResponse
    {
        $config = null;
        $requestType = $request->input('requestType');

        $applies = ($kind === 'section' && $requestType === 'vendor')
            || ($kind === 'image' && $requestType === 'image');
        if (! $applies) {
            return null;
        }

        $storeId = $this->authStoreId();
        if (! $storeId) {
            return null;
        }

        $row = StoreConfig::firstOrNew(['store_id' => $storeId]);

        if ($kind === 'image') {
            $limit = Helpers::get_business_settings('image_upload_limit_for_ai');
            $used = (int) ($row->image_wise_ai_use_count ?? 0);
            $message = translate('messages.You have reached the limit of AI usage via Image.');
        } else {
            $limit = Helpers::get_business_settings('section_wise_ai_limit');
            $used = (int) ($row->section_wise_ai_use_count ?? 0);
            $message = translate('messages.You have reached the limit of AI usage.');
        }

        if ($limit == 0 || $limit === null || $limit <= $used) {
            return response()->json(['message' => $message], 403);
        }

        $config = $row;

        return null;
    }

    private function bumpLimit(?StoreConfig $config, string $kind): void
    {
        if (! $config) {
            return;
        }
        $column = $kind === 'image' ? 'image_wise_ai_use_count' : 'section_wise_ai_use_count';
        if ($config->exists) {
            $config->increment($column);
        } else {
            $config->{$column} = 1;
            $config->save();
        }
    }

    private function authStoreId(): ?int
    {
        try {
            $id = Helpers::get_store_id();

            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveLang(Request $request): string
    {
        return $request->input('langCode') ?: (Helpers::system_default_language() ?: 'en');
    }

    private function disabledResponse(): JsonResponse
    {
        return response()->json(['message' => translate('messages.AI_assistant_is_not_configured')], 403);
    }

    private function emptyResultResponse(): JsonResponse
    {
        return response()->json(['message' => translate('messages.AI_failed_to_generate_a_result_please_try_again')], 422);
    }

    private function failureResponse(\Throwable $e): JsonResponse
    {
        info('Vehicle AI generation failed: ' . $e->getMessage());

        return response()->json(['message' => translate('messages.AI_failed_to_generate_a_result_please_try_again')], 422);
    }
}
