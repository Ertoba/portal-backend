<?php

namespace Modules\Rental\Http\Requests\Vendor\Trip;

class TripPaymentRequest extends TripTargetRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'payment_method' => 'nullable|max:100',
            'transaction_reference' => 'nullable|max:100',
            'payment_status' => 'required|in:paid,unpaid',
        ];
    }

    public function payload(): array
    {
        return [
            'payment_status' => $this->input('payment_status'),
            'payment_method' => $this->input('payment_method'),
            'transaction_reference' => $this->input('transaction_reference'),
        ];
    }
}
