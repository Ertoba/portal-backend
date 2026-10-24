<?php

namespace Modules\Rental\Support;

use ArrayAccess;

class CouponContext implements ArrayAccess
{
    public mixed $is_guest;

    public mixed $user;

    private array $data;

    public function __construct(mixed $couponCode, mixed $providerId, mixed $isGuest, mixed $userId)
    {
        $this->data = ['coupon_code' => $couponCode, 'provider_id' => $providerId];
        $this->is_guest = $isGuest;
        $this->user = $isGuest ? null : (object) ['id' => $userId];
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}
