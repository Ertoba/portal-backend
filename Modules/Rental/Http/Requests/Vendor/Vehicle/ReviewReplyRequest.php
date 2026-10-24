<?php

namespace Modules\Rental\Http\Requests\Vendor\Vehicle;

use App\Http\Requests\BaseRequest;

class ReviewReplyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required',
            'reply' => 'required',
        ];
    }
}
