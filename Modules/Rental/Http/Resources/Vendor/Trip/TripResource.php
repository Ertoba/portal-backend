<?php

namespace Modules\Rental\Http\Resources\Vendor\Trip;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TripResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'user_id' => $this->resource->user_id,
            'provider_id' => $this->resource->provider_id,
            'zone_id' => $this->resource->zone_id,
            'module_id' => $this->resource->module_id,
            'pickup_zone_id' => $this->resource->pickup_zone_id,
            'cash_back_id' => $this->resource->cash_back_id,
            'trip_amount' => $this->resource->trip_amount,
            'discount_on_trip' => $this->resource->discount_on_trip,
            'discount_on_trip_by' => $this->resource->discount_on_trip_by,
            'coupon_discount_amount' => $this->resource->coupon_discount_amount,
            'coupon_discount_by' => $this->resource->coupon_discount_by,
            'coupon_code' => $this->resource->coupon_code,
            'trip_status' => $this->resource->trip_status,
            'payment_status' => $this->resource->payment_status,
            'payment_method' => $this->resource->payment_method,
            'transaction_reference' => $this->resource->transaction_reference,
            'tax_amount' => $this->resource->tax_amount,
            'tax_status' => $this->resource->tax_status,
            'tax_percentage' => $this->resource->tax_percentage,
            'trip_type' => $this->resource->trip_type,
            'additional_charge' => $this->resource->additional_charge,
            'partially_paid_amount' => $this->resource->partially_paid_amount,
            // `trips.distance` is MEASURED kilometres, always (D2), and stays as it is.
            'distance' => $this->resource->distance,
            // §3.4 — the same three keys the store payload carries, so a client is not left
            // holding a bare number it cannot name. Both units are always emitted; only
            // `distance_label` follows `distance_unit`. Additive, so older builds are unaffected.
            ...app(\App\Services\System\DistanceService::class)->keysFromKilometres($this->resource->distance),
            'estimated_hours' => $this->resource->estimated_hours,
            'ref_bonus_amount' => $this->resource->ref_bonus_amount,
            'canceled_by' => $this->resource->canceled_by,
            'attachment' => $this->resource->attachment,
            'cancellation_reason' => $this->resource->cancellation_reason,
            'pickup_location' => $this->resource->pickup_location,
            'destination_location' => $this->resource->destination_location,
            'user_info' => $this->resource->user_info,
            'trip_note' => $this->resource->trip_note,
            'callback' => $this->resource->callback,
            'is_guest' => $this->resource->is_guest,
            'is_hidden' => $this->resource->is_hidden,
            'edited' => $this->resource->edited,
            'checked' => $this->resource->checked,
            'scheduled' => $this->resource->scheduled,
            'schedule_at' => $this->resource->schedule_at,
            'pending' => $this->resource->pending,
            'confirmed' => $this->resource->confirmed,
            'ongoing' => $this->resource->ongoing,
            'completed' => $this->resource->completed,
            'canceled' => $this->resource->canceled,
            'payment_failed' => $this->resource->payment_failed,
            'quantity' => $this->resource->quantity,
            'estimated_trip_end_time' => $this->resource->estimated_trip_end_time,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'pro_discount' => $this->resource->pro_discount,
            'benefit_type' => $this->resource->benefit_type,
            'delivery_fee_reduction_amount' => $this->resource->delivery_fee_reduction_amount,
            'delivery_offer_type' => $this->resource->delivery_offer_type,
        ]);
    }
}
