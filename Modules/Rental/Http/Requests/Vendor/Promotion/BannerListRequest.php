<?php

namespace Modules\Rental\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class BannerListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

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
            'store_id' => $this->vendorStoreId($this),
            'module_id' => $this->vendorStore($this)?->module_id,
            'search' => $this->input('search'),
        ];
    }
}
