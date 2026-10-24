<?php

namespace Modules\Rental\Services;

use App\Services\Admin\AdminSidebarCountService;
use App\Services\Store\StoreService;
use Modules\Rental\Services\Trip\TripsService;

class RentalSidebarCountService
{
    public static function get(?int $moduleId): array
    {
        return AdminSidebarCountService::remember(
            variant: 'rental',
            moduleId: $moduleId,
            callback: fn (): array => self::compute(moduleId: $moduleId)
        );
    }

    private static function compute(?int $moduleId): array
    {
        $statuses = app(TripsService::class)->statusCounts();

        return [
            'count_all' => (int) $statuses->sum(),
            'count_pending' => (int) ($statuses['pending'] ?? 0),
            'count_confirmed' => (int) ($statuses['confirmed'] ?? 0),
            'count_ongoing' => (int) ($statuses['ongoing'] ?? 0),
            'count_completed' => (int) ($statuses['completed'] ?? 0),
            'count_canceled' => (int) ($statuses['canceled'] ?? 0),
            'count_failed' => (int) ($statuses['payment_failed'] ?? 0),
            'count_scheduled' => app(TripsService::class)->countScheduled(),
            'count_new_providers' => app(StoreService::class)->countWithPendingVendor($moduleId),
        ];
    }
}
