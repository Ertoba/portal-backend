<?php

namespace Modules\Rental\Http\Requests\Customer\Vehicle;

use App\Http\Requests\BaseRequest;

class TaxonomyListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
        ];
    }

    public function filters(): array
    {
        return [
            'active' => true,
            'search' => $this->input('search'),
            'user_id' => auth('api')->id(),
        ];
    }
}
