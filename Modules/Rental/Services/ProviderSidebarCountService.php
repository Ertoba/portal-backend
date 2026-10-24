<?php

namespace Modules\Rental\Services;

use Illuminate\Support\Facades\DB;

/**
 * Trip badge counts for the rental provider sidebar.
 *
 * The provider layout includes two sidebar partials on every page: the v1 partial
 * computed these with one raw aggregate, and the v2 partial recomputed the identical
 * numbers as eight separate COUNT(*) queries — sibling @include's do not share locals,
 * so both ran. Nine queries per page for one row of numbers.
 *
 * Resolved once per request here and handed to both partials by a view composer.
 *
 * The SQL is the v1 partial's, unchanged. Trips declares no global scopes and its
 * Pending/Confirmed/Ongoing/Completed/Canceled/PaymentFailed scopes are plain
 * `where('trip_status', ...)` equality, so the collapsed aggregate and the eight
 * scoped counts are numerically identical.
 */
class ProviderSidebarCountService
{
    private const KEYS = [
        'total_trips', 'scheduled_trips', 'pending_trips', 'confirmed_trips',
        'ongoing_trips', 'completed_trips', 'canceled_trips', 'payment_failed_trips',
    ];

    private array $cache = [];

    public function tripCounts(int|string|null $providerId): array
    {
        if ($providerId === null) {
            return array_fill_keys(self::KEYS, 0);
        }

        return $this->cache[$providerId] ??= $this->resolve($providerId);
    }

    private function resolve(int|string $providerId): array
    {
        $row = DB::select('SELECT
                COUNT(*) AS total_trips,
                COALESCE(SUM(CASE WHEN scheduled = 1 THEN 1 ELSE 0 END), 0) AS scheduled_trips,
                COALESCE(SUM(CASE WHEN trip_status = \'pending\' THEN 1 ELSE 0 END), 0) AS pending_trips,
                COALESCE(SUM(CASE WHEN trip_status = \'confirmed\' THEN 1 ELSE 0 END), 0) AS confirmed_trips,
                COALESCE(SUM(CASE WHEN trip_status = \'ongoing\' THEN 1 ELSE 0 END), 0) AS ongoing_trips,
                COALESCE(SUM(CASE WHEN trip_status = \'completed\' THEN 1 ELSE 0 END), 0) AS completed_trips,
                COALESCE(SUM(CASE WHEN trip_status = \'canceled\' THEN 1 ELSE 0 END), 0) AS canceled_trips,
                COALESCE(SUM(CASE WHEN trip_status = \'payment_failed\' THEN 1 ELSE 0 END), 0) AS payment_failed_trips
            FROM trips
            WHERE provider_id = :provider_id', ['provider_id' => $providerId]);

        return $row ? (array) $row[0] : array_fill_keys(self::KEYS, 0);
    }
}
