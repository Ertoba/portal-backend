<?php

namespace Modules\Rental\Http\Requests\Customer\Store;

use App\Http\Requests\BaseRequest;

class ProviderReviewListRequest extends BaseRequest
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
        return ['search' => $this->input('search')];
    }
}
