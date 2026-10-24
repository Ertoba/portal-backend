<?php

namespace Modules\Rental\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

class CouponApplyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required',
            'provider_id' => 'required',
        ];
    }
}
