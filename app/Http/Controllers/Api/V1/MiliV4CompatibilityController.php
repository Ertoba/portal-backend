<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compatibility responses for optional Customer 4 endpoints while the full
 * v4 feature controllers are rolled out. This controller never performs
 * activation or license checks and keeps legacy production responses stable.
 */
class MiliV4CompatibilityController extends Controller
{
    public function topOffer(Request $request): JsonResponse
    {
        return response()->json([
            'module_id' => (int) ($request->header('moduleId') ?? 0),
            'discount' => 0,
            'discount_type' => null,
        ]);
    }

    public function lastOrders(): JsonResponse
    {
        return response()->json([]);
    }

    public function offerItems(Request $request): JsonResponse
    {
        return response()->json([
            'total_size' => 0,
            'limit' => (int) ($request->query('limit', 10)),
            'offset' => (int) ($request->query('offset', 1)),
            'items' => [],
        ]);
    }

    public function offerStores(Request $request): JsonResponse
    {
        return response()->json([
            'total_size' => 0,
            'limit' => (int) ($request->query('limit', 10)),
            'offset' => (int) ($request->query('offset', 1)),
            'stores' => [],
        ]);
    }

    public function exclusiveDeals(Request $request): JsonResponse
    {
        return response()->json([
            'total_size' => 0,
            'limit' => (int) ($request->query('limit', 25)),
            'offset' => (int) ($request->query('offset', 1)),
            'stores' => [],
        ]);
    }

    public function allCarts(): JsonResponse
    {
        return response()->json([]);
    }

    public function aiConversations(Request $request): JsonResponse
    {
        return response()->json([
            'total_size' => 0,
            'limit' => (int) ($request->query('limit', 20)),
            'offset' => (int) ($request->query('offset', 1)),
            'conversations' => [],
        ]);
    }

    public function aiMessages(Request $request): JsonResponse
    {
        return response()->json([
            'total_size' => 0,
            'limit' => (int) ($request->query('limit', 20)),
            'offset' => (int) ($request->query('offset', 1)),
            'messages' => [],
        ]);
    }

    public function savedFiles(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['errors' => [['code' => 'unauthorized']]], 401);

        $files = UserFile::where('user_id', $user->id)
            ->where('type', 'prescription')
            ->latest()
            ->get(['file_name', 'storage'])
            ->map(fn (UserFile $file) => [
                'file_name' => $file->file_name,
                'image_full_url' => $file->image_full_url,
            ])->values();

        return response()->json(['saved_files' => $files]);
    }
}
