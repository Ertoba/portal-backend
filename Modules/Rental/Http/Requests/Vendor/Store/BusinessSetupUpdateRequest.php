<?php

namespace Modules\Rental\Http\Requests\Vendor\Store;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class BusinessSetupUpdateRequest extends BaseRequest
{
    use ApiRequestContextTrait;
    public function rules(): array
    {
        return [
            'contact_number' => $this->phoneRule(unique: 'stores,phone,' . $this->vendorStoreId($this)),
            'schedule_trip' => 'required|boolean',
            'gst' => 'required_if:gst_status,1',
            'minimum_pickup_time' => 'required|numeric',
            'maximum_pickup_time' => 'required|numeric',
            'pickup_time_type' => 'required|in:min,hours,days',
            'logo' => $this->imageRule(),
            'cover_photo' => $this->imageRule(),
            'meta_image' => $this->imageRule(),
            'translations' => 'required|array|min:4',
        ];
    }
    public function messages(): array
    {
        return [
            'gst.required_if' => translate('GST can not be empty'),
            'translations.required' => translate('messages.Name and address in english is required'),
            'translations.array' => translate('messages.Name and address in english is required'),
            'translations.min' => translate('messages.Name and address in english is required'),
        ];
    }
    public function payload(): array
    {
        $translations = $this->translationRows($this);

        return [
            'store' => [
                'name' => $translations[0]['value'] ?? null,
                'address' => $translations[1]['value'] ?? null,
                'meta_title' => $translations[2]['value'] ?? null,
                'meta_description' => $translations[3]['value'] ?? null,
                'phone' => $this->input('contact_number'),
                'schedule_order' => $this->input('schedule_trip'),
                'gst' => json_encode(['status' => $this->input('gst_status'), 'code' => $this->input('gst')]),
                'delivery_time' => $this->input('minimum_pickup_time') . '-' . $this->input('maximum_pickup_time') . ' ' . $this->input('pickup_time_type'),
                'pickup_zone_id' => $this->input('pickup_zone_id') ?? json_encode([]),
            ],
            'translations' => $translations,
            'logo_file' => $this->hasFile('logo') ? $this->file('logo') : null,
            'cover_photo_file' => $this->hasFile('cover_photo') ? $this->file('cover_photo') : null,
            'meta_image_file' => $this->hasFile('meta_image') ? $this->file('meta_image') : null,
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge(['translations' => $this->translationRows($this)]);
    }
}
