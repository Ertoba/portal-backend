<?php

namespace Modules\Rental\Http\Resources\Vendor\Vehicle;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class DriverResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'provider_id' => $this->resource->provider_id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'identity_type' => $this->resource->identity_type,
            'identity_number' => $this->resource->identity_number,
            'identity_image' => $this->resource->identity_image,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'trips_count' => $this->resource->trips_count,
            'total_trip_completed' => $this->resource->total_trip_completed,
            'total_trip_canceled' => $this->resource->total_trip_canceled,
            'total_trip_ongoing' => $this->resource->total_trip_ongoing,
            'image_full_url' => $this->resource->image_full_url,
            'identity_image_full_url' => $this->resource->identity_image_full_url,
        ]);
    }
}
