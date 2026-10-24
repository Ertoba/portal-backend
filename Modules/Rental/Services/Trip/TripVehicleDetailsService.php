<?php

namespace Modules\Rental\Services\Trip;

use App\Services\BaseService;
use Modules\Rental\Entities\TripVehicleDetails;

class TripVehicleDetailsService extends BaseService
{
    public function findForTrip(mixed $tripId, mixed $detailId): ?TripVehicleDetails
    {
        return TripVehicleDetails::where('trip_id', $tripId)->where('id', $detailId)->first();
    }

    public function findOrNewForIdentity(mixed $tripId, mixed $vehicleId, mixed $identityId): TripVehicleDetails
    {
        return TripVehicleDetails::where([
            'trip_id' => $tripId,
            'vehicle_id' => $vehicleId,
            'vehicle_identity_id' => $identityId,
        ])->firstOrNew();
    }

    public function deleteIdentitiesExcept(mixed $tripId, mixed $vehicleId, array $identityIds): void
    {
        TripVehicleDetails::where(['trip_id' => $tripId, 'vehicle_id' => $vehicleId])
            ->whereNotIn('vehicle_identity_id', $identityIds)
            ->delete();
    }

    public function markCompletedForTrip(mixed $tripId): void
    {
        TripVehicleDetails::where('trip_id', $tripId)->update(['is_completed' => 1]);
    }
}
