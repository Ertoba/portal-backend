<?php

namespace Modules\Rental\Http\Requests\Customer\Cart;

class CartUserDataRequest extends CartListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'rental_type' => 'required|in:hourly,distance_wise,day_wise',
            'estimated_hours' => 'required_if:rental_type,hourly|required_if:rental_type,day_wise',
            'distance' => 'required_if:rental_type,distance_wise',
            'destination_time' => 'required_if:rental_type,distance_wise',
        ];
    }

    public function messages(): array
    {
        return [
            'destination_time.required_if' => translate('Destination address is required when rental type is distance wise'),
            'estimated_hours.*' => $this->input('rental_type') === 'hourly'
                ? translate('Estimated hours is required when rental type is hourly')
                : translate('Estimated day is required when rental type is day wise'),
        ];
    }
}
