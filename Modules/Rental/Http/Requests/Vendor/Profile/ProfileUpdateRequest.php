<?php

namespace Modules\Rental\Http\Requests\Vendor\Profile;

use App\Http\Requests\Vendor\Profile\ProfileUpdateRequest as BaseProfileUpdateRequest;

class ProfileUpdateRequest extends BaseProfileUpdateRequest
{
    public function messages(): array
    {
        return [
            'f_name.required' => translate('messages.First name is required'),
            'l_name.required' => translate('messages.Last name is required'),
        ];
    }
}
