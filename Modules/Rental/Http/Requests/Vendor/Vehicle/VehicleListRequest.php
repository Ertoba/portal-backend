<?php

namespace Modules\Rental\Http\Requests\Vendor\Vehicle;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class VehicleListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
        ];
    }

    public function filters(): array
    {
        return [
            'provider_id' => $this->vendorStoreId($this),
            'search' => $this->input('search'),
            'category_id' => $this->input('category_id'),
            'brand_id' => $this->input('brand_id'),
            'air_condition' => $this->input('air_condition'),
            'transmission_type' => $this->input('transmission_type'),
            'vehicle_type' => $this->input('vehicle_type'),
            'fuel_type' => $this->input('fuel_type'),
            'seating_capacity' => $this->input('seating_capacity'),
        ];
    }
}
