<?php

namespace Modules\Rental\Http\Requests\Customer\Wishlist;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class WishlistTargetRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'vehicle_id' => 'required_without:provider_id',
            'provider_id' => 'required_without:vehicle_id',
        ];
    }

    public function target(): array
    {
        return [
            'vehicle_id' => $this->input('vehicle_id'),
            'provider_id' => $this->input('provider_id'),
        ];
    }

    public function targetCount(): int
    {
        return count(array_filter($this->target(), fn ($value) => ! is_null($value) && $value !== ''));
    }

    /**
     * Same convention as the core wishlist (App\Http\Requests\Customer\Wishlist\WishlistTargetRequest):
     * the message names what was acted on, so removing returns "Vehicle removed from
     * favorites" instead of the generic "Deleted successfully".
     */
    public function targetLabel(string $action): string
    {
        return match (true) {
            (bool) $this->input('provider_id') => "Provider {$action} favorites",
            default => "Vehicle {$action} favorites",
        };
    }
}
