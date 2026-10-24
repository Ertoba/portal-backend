<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

use App\Rules\EmailAddress;
use App\Rules\PhoneNumber;

class TripBookingRequest extends TripContextRequest
{
    public function rules(): array
    {
        $presence = $this->tripUser() ? 'nullable' : 'required';

        return parent::rules() + [
            'trip_amount' => 'required|numeric',
            'trip_type' => 'required|in:hourly,distance_wise,day_wise',
            'provider_id' => 'required|numeric',
            'contact_person_name' => $presence,
            'contact_person_number' => PhoneNumber::rules($presence),
            'contact_person_email' => EmailAddress::rules($presence),
        ];
    }

    public function bookingInput(): array
    {
        return [
            'provider_id' => $this->input('provider_id'),
            'trip_type' => $this->input('trip_type'),
            'schedule_at' => $this->input('schedule_at'),
            'scheduled' => $this->input('scheduled') ?? 0,
            'coupon_code' => $this->input('coupon_code'),
            'additional_note' => $this->input('additional_note'),
            'zone_ids' => $this->zoneIds($this),
        ];
    }

    public function contactInfo(): array
    {
        $user = $this->tripUser();

        return [
            'contact_person_name' => $this->input('contact_person_name')
                ?: ($user ? trim($user->f_name . ' ' . $user->l_name) : ''),
            'contact_person_number' => $this->input('contact_person_number') ?: ($user->phone ?? ''),
            'contact_person_email' => $this->input('contact_person_email') ?: ($user->email ?? ''),
        ];
    }
}
