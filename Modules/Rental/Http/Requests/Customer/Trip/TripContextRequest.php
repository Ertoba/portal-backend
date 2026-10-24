<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class TripContextRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return ['guest_id' => $this->tripUser() ? 'nullable' : 'required'];
    }

    public function tripUser(): mixed
    {
        return $this->input('user');
    }

    public function context(): array
    {
        return [
            'user_id' => $this->tripUser() ? $this->tripUser()->id : $this->input('guest_id'),
            'is_guest' => $this->tripUser() ? 0 : 1,
            'module_id' => getModuleId($this->header('moduleId')),
            'zone_ids' => $this->zoneIds($this),
        ];
    }
}
