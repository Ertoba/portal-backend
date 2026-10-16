<?php

namespace Modules\Rental\Services\Trip;

use Modules\Rental\Entities\Trips;
use App\Services\BaseService;

class TripsService extends BaseService
{
    public function countUnpaidActive(mixed $customerId, mixed $providerId, array $statuses): int
    {
        return Trips::where('user_id', $customerId)
            ->where('provider_id', $providerId)
            ->whereIn('trip_status', $statuses)
            ->where('payment_status', 'unpaid')
            ->count();
    }

    public function baseQuery(array $filters = []): mixed
    {
        return Trips::where('user_id', $filters['user_id'] ?? null)
            ->where('is_guest', ($filters['is_guest'] ?? 1) === 0 ? 0 : 1)
            ->where('module_id', $filters['module_id'] ?? null);
    }

    public function runningQuery(mixed $userId, array $closedStatuses): mixed
    {
        return Trips::where('user_id', $userId)
            ->whereNotIn('trip_status', $closedStatuses)
            ->selectRaw("id, 'trip' as order_type, trip_status as status, created_at");
    }

    public function couponLimitReached(mixed $userId, mixed $coupon): bool
    {
        return Trips::couponLimitReached(userId: $userId, coupon: $coupon);
    }


    public function query(): mixed
    {
        return Trips::query();
    }


    public function statusCounts(): mixed
    {
        return Trips::statusCounts();
    }

    public function countScheduled(): int
    {
        return Trips::Scheduled()->count();
    }

}
