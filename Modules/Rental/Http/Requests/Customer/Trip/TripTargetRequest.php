<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripTargetRequest extends TripContextRequest
{
    public function rules(): array
    {
        return parent::rules() + ['trip_id' => 'required'];
    }

    public function tripId(): mixed
    {
        return $this->input('trip_id');
    }
}
