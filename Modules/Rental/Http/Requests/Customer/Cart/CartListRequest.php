<?php

namespace Modules\Rental\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class CartListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'guest_id' => $this->cartUser() ? 'nullable' : 'required',
        ];
    }

    public function cartUser(): mixed
    {
        return $this->input('user');
    }

    public function context(): array
    {
        return [
            'user_id' => $this->cartUser() ? $this->cartUser()->id : $this->input('guest_id'),
            'is_guest' => $this->cartUser() ? 0 : 1,
            'module_id' => getModuleId($this->header('moduleId')),
            'zone_ids' => $this->zoneIds($this),
        ];
    }

    public function inputs(): array
    {
        return [
            'pickup_location' => $this->input('pickup_location'),
            'destination_location' => $this->input('destination_location'),
            'pickup_time' => $this->input('pickup_time'),
            'rental_type' => $this->input('rental_type'),
            'estimated_hours' => $this->input('estimated_hours'),
            'distance' => $this->input('distance'),
            'destination_time' => $this->input('destination_time'),
        ];
    }
}
