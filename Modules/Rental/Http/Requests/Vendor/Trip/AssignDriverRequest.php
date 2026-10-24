<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

use App\CentralLogics\Helpers;

class AssignDriverRequest extends TripTargetRequest
{
    public function rules(): array
    {
        return parent::rules() + ['driver_ids' => 'required'];
    }

    public function driverMap(): array
    {
        return (array) Helpers::decodeJsonToArray($this->input('driver_ids'));
    }
}
