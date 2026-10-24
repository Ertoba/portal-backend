<?php

namespace Modules\Rental\Http\Requests\Customer\Wishlist;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class WishlistListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
        ];
    }

    public function filters(): array
    {
        return [
            'user_id' => $this->user()->id,
            'zone_ids' => $this->zoneIds($this),
            'module_id' => $this->currentModuleId(),
            'longitude' => $this->header('longitude'),
            'latitude' => $this->header('latitude'),
        ];
    }
}
