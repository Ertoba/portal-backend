<?php

namespace Modules\Rental\Http\Requests\Customer\Cart;

use App\CentralLogics\Helpers;

class CartRemoveManyRequest extends CartListRequest
{
    public function rules(): array
    {
        return parent::rules() + ['cart_ids' => 'required'];
    }

    public function cartIds(): array
    {
        return (array) Helpers::decodeJsonToArray($this->input('cart_ids'));
    }
}
