<?php

namespace Modules\Rental\Http\Requests\Vendor\Ai;

use App\Http\Requests\BaseRequest;

class TitleAndDescriptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'langCode' => 'nullable|string|max:10',
        ];
    }
}
