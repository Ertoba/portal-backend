<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripCancelRequest extends TripTargetRequest
{
    public function rules(): array
    {
        return parent::rules() + ['cancellation_reason' => 'nullable|max:255'];
    }
}
