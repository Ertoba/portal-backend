<?php

namespace Modules\Rental\Http\Requests\Customer\Trip;

class TripPaymentRequest extends TripTargetRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'payment_method' => 'required|in:cash_payment,wallet,partial_payment,digital_payment',
            'payment_gateway' => 'required_if:payment_method,digital_payment,partial_payment',
            'callback_url' => 'required_if:payment_method,digital_payment',
            'payment_platform' => 'required_if:payment_method,digital_payment',
        ];
    }

    public function gatewayPayload(): array
    {
        return [
            'gateway' => $this->input('payment_gateway'),
            'callback' => $this->input('callback_url'),
            'platform' => $this->input('payment_platform') ?? 'web',
        ];
    }
}
