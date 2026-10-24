<?php

namespace Modules\Rental\Http\Requests\Customer\Vehicle;

use App\CentralLogics\Helpers;
use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class VehicleSearchRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'sortby_price' => 'nullable|in:asc,desc',
            'trip_type' => 'nullable|in:hourly,distance_wise,day_wise,provider_wise',
        ];
    }

    public function filters(): array
    {
        return [
            'zone_id' => $this->zoneIds($this),
            'user_id' => auth('api')->id(),
            'name' => $this->input('name'),
            'trip_type' => $this->input('trip_type'),
            'provider_id' => $this->input('provider_id'),
            'date' => $this->input('date'),
            'max_price' => $this->input('max_price'),
            'min_price' => $this->input('min_price') ?? 0,
            'brand_ids' => Helpers::decodeJsonToArray($this->input('brand_ids')),
            'category_ids' => Helpers::decodeJsonToArray($this->input('category_ids')),
            'seating_capacity' => Helpers::decodeJsonToArray($this->input('seating_capacity')),
            'air_condition' => $this->input('air_condition'),
            'no_air_condition' => $this->input('no_air_condition'),
            'transmission_type' => $this->input('transmission_type'),
            'vehicle_type' => $this->input('vehicle_type'),
            'fuel_type' => $this->input('fuel_type'),
            'sortby_price' => $this->input('sortby_price'),
            'top_rated' => $this->input('top_rated'),
        ];
    }

    public function pickupLocation(): array
    {
        $location = Helpers::decodeJsonToArray($this->input('pickup_location'));

        return [data_get($location, 'lat'), data_get($location, 'lng')];
    }
}
