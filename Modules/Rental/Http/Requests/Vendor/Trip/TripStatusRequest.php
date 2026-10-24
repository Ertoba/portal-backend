<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

class TripStatusRequest extends TripTargetRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'cancellation_reason' => 'nullable|max:255',
            'trip_status' => 'required|in:confirmed,ongoing,completed,canceled',
        ];
    }

    public function payload(): array
    {
        return [
            'trip_status' => $this->input('trip_status'),
            'cancellation_reason' => $this->input('cancellation_reason'),
        ];
    }
}
