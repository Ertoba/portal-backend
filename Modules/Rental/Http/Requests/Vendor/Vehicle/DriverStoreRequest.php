<?php

namespace Modules\Rental\Http\Requests\Vendor\Vehicle;

use App\Http\Requests\BaseRequest;
use App\Rules\EmailAddress;
use App\Traits\Api\ApiRequestContextTrait;

class DriverStoreRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => EmailAddress::rules('required', 'vehicle_drivers,email' . $this->ignoredId()),
            'phone' => $this->phoneRule('required', 'vehicle_drivers,phone' . $this->ignoredId()),
            'identity_type' => 'required|string',
            'identity_number' => 'required|string|max:50',
            'image' => $this->imageRule($this->imagePresence()),
            'identity_image' => 'nullable|array',
            'identity_image.*' => $this->imageRule(),
        ];
    }

    public function payload(): array
    {
        return [
            'provider_id' => $this->vendorStoreId($this),
            'first_name' => $this->input('first_name'),
            'last_name' => $this->input('last_name'),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'identity_type' => $this->input('identity_type'),
            'identity_number' => $this->input('identity_number'),
            'image_file' => $this->hasFile('image') ? $this->file('image') : null,
            'identity_files' => $this->hasFile('identity_image') ? $this->file('identity_image') : [],
        ];
    }

    protected function ignoredId(): string
    {
        return '';
    }

    protected function imagePresence(): string
    {
        return 'required';
    }
}
