<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SearchLog;
use Illuminate\Support\Facades\Cache;

class TrendingSearchService
{
    public function getTrending(?int $moduleId, string $zoneId, bool $cache = true): array
    {
        $cacheKey = 'trending_searches_' . md5($zoneId . '_' . ($moduleId ?? 'global'));

        $zoneIds = $this->parseZoneIds($zoneId);

        $compute = function () use ($moduleId, $zoneIds) {
            $trending = SearchLog::selectRaw('keyword, COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN CONCAT("u_", user_id) ELSE CONCAT("g_", guest_id) END) as unique_users')
                ->when($moduleId, function ($q) use ($moduleId) {
                    $q->where('module_id', $moduleId);
                })
                ->when(! empty($zoneIds), function ($q) use ($zoneIds) {
                    $q->where(function ($qq) use ($zoneIds) {
                        foreach ($zoneIds as $z) {
                            $qq->orWhereRaw('JSON_CONTAINS(zone_id, ?)', [(string) $z]);
                        }
                    });
                })
                ->where('created_at', '>=', now()->subHours(6))
                ->where('result_count', '>', 0)
                ->whereRaw('LENGTH(TRIM(keyword)) >= 2')
                ->groupBy('keyword')
                ->havingRaw('unique_users >= 2')
                ->orderByDesc('unique_users')
                ->limit(10)
                ->pluck('keyword')
                ->toArray();

            if (! empty($trending)) {
                return $trending;
            }

            $fallback = Category::where('status', 1)
                ->where('position', 0)
                ->when($moduleId, function ($q) use ($moduleId) {
                    $q->where('module_id', $moduleId);
                })
                ->orderByDesc('priority')
                ->limit(10)
                ->pluck('name')
                ->toArray();

            shuffle($fallback);

            return $fallback;
        };

        if (! $cache) {
            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
            }
            return $compute();
        }

        return Cache::remember($cacheKey, now()->addMinutes(15), $compute);
    }

    public function log(string $keyword, ?int $userId, ?string $guestId, int $moduleId, string $zoneId, int $resultCount): void
    {
        if (strlen(trim($keyword)) < 2) {
            return;
        }

        SearchLog::create([
            'keyword'      => strtolower(trim($keyword)),
            'user_id'      => $userId,
            'guest_id'     => $guestId,
            'module_id'    => $moduleId,
            'zone_id'      => $zoneId,
            'result_count' => $resultCount,
        ]);
    }

    private function parseZoneIds(string $zoneId): array
    {
        $raw = trim($zoneId);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [$raw];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($v) => (int) $v, $decoded),
            fn ($v) => $v > 0
        )));
    }
}
