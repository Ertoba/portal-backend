<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

use App\CentralLogics\Helpers;

class AssignVehicleRequest extends TripTargetRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'vehicle_identity_ids' => 'required',
            'trip_details_id' => 'required',
            'vehicle_id' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'vehicle_id' => $this->input('vehicle_id'),
            'trip_details_id' => $this->input('trip_details_id'),
            'vehicle_identity_ids' => (array) Helpers::decodeJsonToArray($this->input('vehicle_identity_ids')),
        ];
    }
}
