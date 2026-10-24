<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class TripListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
        ];
    }

    public function filters(string $tripStatus): array
    {
        return [
            'provider_id' => $this->vendorStoreId($this),
            'trip_status' => $tripStatus,
        ];
    }
}
