<?php

namespace Modules\Rental\Http\Resources\Vendor\Trip;

use Illuminate\Http\Request;

class TripDetailResource extends TripResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'customer' => $this->whenLoaded('customer'),
            'vehicle_identity' => $this->whenLoaded('vehicle_identity'),
            'trip_details' => $this->whenLoaded('trip_details'),
        ]);
    }
}
