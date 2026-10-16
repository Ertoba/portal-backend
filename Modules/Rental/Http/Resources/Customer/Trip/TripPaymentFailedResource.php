<?php

namespace Modules\Rental\Http\Resources\Customer\Trip;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TripPaymentFailedResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $zone = $this->resource->relationLoaded('zone') ? $this->resource->zone : null;
        $module = $this->resource->relationLoaded('module') ? $this->resource->module : null;

        return array_merge(parent::toArray($request), [
            'type' => 'rental',
            'cash_on_delivery' => (bool) (Helpers::get_business_settings('cash_on_delivery')['status'] && $zone?->cash_on_delivery),
            'digital_payment' => (bool) (Helpers::get_business_settings('digital_payment')['status'] && $zone?->digital_payment),
            'offline_payment' => false,
            'maximum_cod_order_amount' => $module?->zones->first()?->pivot?->maximum_cod_order_amount ?? 0,
            'trip_id' => (int) $this->resource->id,
            'trip_amount' => (float) $this->resource->trip_amount,
            'partially_paid_amount' => (float) $this->resource->partially_paid_amount,
            'trip_type' => $this->resource->trip_type,
            'trip_status' => $this->resource->trip_status,
            'user_id' => $this->resource->is_guest ? null : $this->resource->user_id,
            'guest_id' => $this->resource->is_guest ? $this->resource->user_id : null,
            'zone_id' => $this->resource->zone_id,
            'module' => $module?->id,
            'module_type' => $module?->module_type,
            'payment_status' => $this->resource->payment_status,
            'payment_method' => $this->resource->payment_method,
            'contact_person_number' => $this->contactPersonNumber(),
        ]);
    }

    private function contactPersonNumber(): ?string
    {
        $info = $this->resource->user_info;
        $info = is_array($info) ? $info : json_decode((string) $info, true);

        return $info['contact_person_number'] ?? null;
    }
}
