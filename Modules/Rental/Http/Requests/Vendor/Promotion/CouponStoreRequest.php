<?php

namespace Modules\Rental\Http\Requests\Vendor\Promotion;

use App\Http\Requests\Vendor\Promotion\CouponStoreRequest as BaseCouponStoreRequest;

class CouponStoreRequest extends BaseCouponStoreRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|unique:coupons|max:100',
            'start_date' => 'required',
            'expire_date' => 'required',
            'coupon_type' => 'required|in:free_delivery,default',
            'discount' => 'required_if:coupon_type,default',
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->translationRows($this)) < 1) {
                $validator->errors()->add('translations', translate('messages.Title in English is required'));
            }
        });
    }
}
