<?php

namespace Modules\Rental\Http\Requests\Vendor\Vehicle;

use App\Http\Requests\BaseRequest;

class TaxonomyListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
        ];
    }
}
