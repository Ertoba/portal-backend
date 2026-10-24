<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class TripTargetRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return ['trip_id' => 'required'];
    }

    public function tripId(): mixed
    {
        return $this->input('trip_id');
    }

    public function providerId(): mixed
    {
        return $this->vendorStoreId($this);
    }
}
