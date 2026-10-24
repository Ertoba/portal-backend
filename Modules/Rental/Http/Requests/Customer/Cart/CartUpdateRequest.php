<?php

namespace Modules\Rental\Http\Requests\Customer\Cart;

class CartUpdateRequest extends CartListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'cart_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
