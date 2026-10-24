<?php

namespace Modules\Rental\Http\Requests\Vendor\Vehicle;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class VehicleStoreRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'category_id' => 'required',
            'brand_id' => 'required',
            'translations' => 'required',
            'discount_type' => 'required|in:percent,amount',
            'discount_price' => ['nullable', 'numeric', fn ($attribute, $value, $fail) => $this->validateDiscount($value, $fail)],
            'vehicle' => 'required|array',
            'vehicle.vin_number' => 'required|array|min:1',
            'vehicle.license_plate_number' => 'required|array|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => translate('messages.Category required'),
            'brand_id.required' => translate('messages.Brand required'),
            'discount_price.numeric' => translate('messages.Discount must be numeric'),
            'discount_type.required' => translate('messages.Discount type required'),
            'discount_type.in' => translate('messages.Discount type invalid'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->translationRows($this)) < 2) {
                $validator->errors()->add('translations', translate('messages.Name and description in english is required'));
            }
        });
    }

    public function payload(): array
    {
        $translations = $this->translationRows($this);
        $store = $this->vendorStore($this);

        return [
            'name' => $translations[0]['value'] ?? null,
            'description' => $translations[1]['value'] ?? null,
            'translations' => $translations,
            'zone_id' => $store?->zone_id ?? 0,
            'provider_id' => $store?->id,
            'brand_id' => $this->input('brand_id'),
            'category_id' => $this->input('category_id'),
            'model' => $this->input('model'),
            'type' => $this->input('type'),
            'engine_capacity' => $this->input('engine_capacity'),
            'engine_power' => $this->input('engine_power'),
            'seating_capacity' => $this->input('seating_capacity'),
            'air_condition' => $this->input('air_condition') ? 1 : 0,
            'multiple_vehicles' => $this->input('multiple_vehicles') ? 1 : 0,
            'fuel_type' => $this->input('fuel_type'),
            'transmission_type' => $this->input('transmission_type'),
            'trip_hourly' => $this->input('trip_hourly') ? 1 : 0,
            'trip_distance' => $this->input('trip_distance') ? 1 : 0,
            'trip_day_wise' => $this->input('trip_day_wise') ? 1 : 0,
            'hourly_price' => $this->input('hourly_price') ?? 0.00,
            'distance_price' => $this->input('distance_price') ?? 0.00,
            'day_wise_price' => $this->input('day_wise_price') ?? 0,
            'discount_price' => $this->input('discount_price') ?? 0.00,
            'discount_type' => $this->input('discount_type'),
            'tag' => $this->input('tag'),
            'identities' => $this->identities(),
            'thumbnail_file' => $this->hasFile('thumbnail') ? $this->file('thumbnail') : null,
            'image_files' => $this->hasFile('images') ? $this->file('images') : [],
            'document_files' => $this->hasFile('documents') ? $this->file('documents') : [],
        ];
    }

    private function identities(): array
    {
        $vins = (array) $this->input('vehicle.vin_number', []);
        $plates = (array) $this->input('vehicle.license_plate_number', []);

        $rows = [];
        foreach ($vins as $index => $vin) {
            $rows[] = ['vin_number' => $vin, 'license_plate_number' => $plates[$index] ?? null];
        }

        return $rows;
    }

    private function validateDiscount(mixed $value, callable $fail): void
    {
        $prices = array_filter([
            $this->input('trip_hourly') ? (float) ($this->input('hourly_price') ?? 0) : 0,
            $this->input('trip_distance') ? (float) ($this->input('distance_price') ?? 0) : 0,
            $this->input('trip_day_wise') ? (float) ($this->input('day_wise_price') ?? 0) : 0,
        ], fn ($price) => $price > 0);

        $applicable = $prices ? min($prices) : 0;

        if ($this->input('discount_type') === 'percent' && $value >= 100) {
            $fail(translate('messages.A percentage discount must be less than the full price'));
        }

        if ($this->input('discount_type') === 'amount' && $value > $applicable) {
            $fail(translate('messages.Discount cannot exceed price'));
        }
    }
}
