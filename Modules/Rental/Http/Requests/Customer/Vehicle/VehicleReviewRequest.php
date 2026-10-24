<?php

namespace Modules\Rental\Http\Requests\Customer\Vehicle;

use App\Http\Requests\BaseRequest;
use Modules\Rental\Entities\Trips;

class VehicleReviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'vehicle_id' => 'required',
            'trip_id' => 'required|exists:' . (new Trips())->getTable() . ',id',
            'vehicle_identity_id' => 'required',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
            'attachment' => 'nullable|array',
            'attachment.*' => $this->imageRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'trip_id.exists' => translate('No data found'),
        ];
    }

    public function reviewKeys(): array
    {
        return [
            'vehicle_id' => $this->input('vehicle_id'),
            'user_id' => $this->user()->id,
            'trip_id' => $this->input('trip_id'),
            'vehicle_identity_id' => $this->input('vehicle_identity_id'),
        ];
    }

    public function payload(): array
    {
        $trip = Trips::find($this->input('trip_id'));

        return $this->reviewKeys() + [
            'provider_id' => $trip->provider_id,
            'module_id' => $trip->module_id,
            'comment' => $this->input('comment'),
            'rating' => (int) $this->input('rating'),
            'attachment_files' => $this->hasFile('attachment') ? $this->file('attachment') : [],
        ];
    }
}
