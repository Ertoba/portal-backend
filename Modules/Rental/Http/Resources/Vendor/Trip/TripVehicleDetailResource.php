<?php

namespace Modules\Rental\Http\Resources\Vendor\Trip;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TripVehicleDetailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'trip_id' => $this->resource->trip_id,
            'vehicle_id' => $this->resource->vehicle_id,
            'trip_details_id' => $this->resource->trip_details_id,
            'vehicle_identity_id' => $this->resource->vehicle_identity_id,
            'vehicle_driver_id' => $this->resource->vehicle_driver_id,
            'estimated_trip_end_time' => $this->resource->estimated_trip_end_time,
            'is_completed' => $this->resource->is_completed,
        ]);
    }
}
