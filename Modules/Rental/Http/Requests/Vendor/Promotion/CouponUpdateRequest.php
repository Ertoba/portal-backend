<?php

namespace Modules\Rental\Http\Requests\Vendor\Promotion;

class CouponUpdateRequest extends CouponStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => 'required|max:100|unique:coupons,code,' . $this->route('id'),
        ]);
    }
}
