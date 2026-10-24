<?php

namespace Modules\Rental\Http\Resources\Customer\Trip;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TripResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'trip_amount' => $this->resource->trip_amount,
            'discount_on_trip' => $this->resource->discount_on_trip,
            'coupon_discount_amount' => $this->resource->coupon_discount_amount,
            'pro_discount' => $this->resource->pro_discount,
            'trip_status' => $this->resource->trip_status,
            'payment_status' => $this->resource->payment_status,
            'tax_amount' => $this->resource->tax_amount,
            'tax_status' => $this->resource->tax_status,
            'tax_percentage' => $this->resource->tax_percentage,
            'trip_type' => $this->resource->trip_type,
            'additional_charge' => $this->resource->additional_charge,
            // `trips.distance` is MEASURED kilometres, always (D2), and stays as it is.
            'distance' => $this->resource->distance,
            // §3.4 — the same three keys the store payload carries, so a client is not left
            // holding a bare number it cannot name. Both units are always emitted; only
            // `distance_label` follows `distance_unit`. Additive, so older builds are unaffected.
            ...app(\App\Services\System\DistanceService::class)->keysFromKilometres($this->resource->distance),
            'estimated_hours' => $this->resource->estimated_hours,
            'pickup_location' => $this->resource->pickup_location,
            'destination_location' => $this->resource->destination_location,
            'trip_note' => $this->resource->trip_note,
            'schedule_at' => $this->resource->schedule_at,
            'quantity' => $this->resource->quantity,
            'estimated_trip_end_time' => $this->resource->estimated_trip_end_time,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'trip_details' => $this->whenLoaded('trip_details'),
            'vehicle_identity' => $this->whenLoaded('vehicle_identity'),
            'provider' => $this->whenLoaded('provider'),
        ]);
    }
}
