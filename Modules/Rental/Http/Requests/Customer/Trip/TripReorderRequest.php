<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripReorderRequest extends TripContextRequest
{
    public function rules(): array
    {
        return ['trip_id' => 'required|integer'];
    }

    public function tripId(): mixed
    {
        return $this->input('trip_id');
    }
}
