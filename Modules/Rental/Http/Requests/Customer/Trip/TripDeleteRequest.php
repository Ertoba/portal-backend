<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripDeleteRequest extends TripTargetRequest
{
    public function tripIds(): array
    {
        return is_array($this->input('trip_id')) ? $this->input('trip_id') : [$this->input('trip_id')];
    }
}
