<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripListRequest extends TripContextRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'trip_status' => 'nullable|in:pending,confirmed,ongoing,completed,canceled,payment_failed',
        ];
    }

    public function filters(?string $type = null): array
    {
        return $this->context() + [
            'type' => $type,
            'search' => $this->input('search'),
            'trip_status' => $this->input('trip_status'),
            'provider_id' => $this->input('provider_id'),
        ];
    }
}
