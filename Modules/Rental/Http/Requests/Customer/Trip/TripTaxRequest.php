<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripTaxRequest extends TripContextRequest
{
    public function bookingInput(): array
    {
        return [
            'provider_id' => $this->input('provider_id'),
            'trip_type' => $this->input('trip_type'),
            'schedule_at' => $this->input('schedule_at'),
            'scheduled' => $this->input('scheduled') ?? 0,
            'coupon_code' => $this->input('coupon_code'),
            'additional_note' => $this->input('additional_note'),
            'zone_ids' => $this->zoneIds($this),
        ];
    }
}
