<?php

namespace Modules\Rental\Http\Requests\Customer\Vehicle;

class ProviderVehicleRequest extends VehicleSearchRequest
{
    public function rules(): array
    {
        return parent::rules() + ['provider_id' => 'required'];
    }
}
