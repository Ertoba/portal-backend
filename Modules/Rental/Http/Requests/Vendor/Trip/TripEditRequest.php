<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

use App\CentralLogics\Helpers;
use Carbon\Carbon;
use Modules\Rental\Entities\Trips;

class TripEditRequest extends TripTargetRequest
{
    public function pickupLocation(Trips $trip): mixed
    {
        return $this->input('pickup_location') ?: $trip->pickup_location;
    }

    public function payload(Trips $trip): array
    {
        $scheduleAt = Carbon::parse($this->input('schedule_at') ?: $trip->schedule_at);
        $estimatedHours = $this->input('estimated_hours') ?? $trip->estimated_hours;

        return [
            'destinationLocation' => json_encode($this->input('destination_location') ?: $trip->destination_location),
            'pickupLocation' => json_encode($this->pickupLocation($trip)),
            'scheduleAt' => $scheduleAt,
            'estimatedHours' => $estimatedHours,
            'distance' => $this->input('distance') ?? $trip->distance,
            'scheduled' => $this->input('scheduled') ?? $trip->scheduled,
            'estimatedTripEndTime' => $scheduleAt->copy()->addHours(ceil(
                in_array($trip->rental_type, ['hourly', 'day_wise'], true)
                    ? $estimatedHours
                    : ($this->input('destination_time') ?? $trip->destination_time)
            )),
            'vehicleQuantities' => (array) Helpers::decodeJsonToArray($this->input('vehicle_quantities') ?? '[]'),
            'modifiedPrices' => (array) Helpers::decodeJsonToArray($this->input('modified_prices') ?? '[]'),
            'taxPercentage' => $this->vendorStore($this)?->tax,
        ];
    }
}
