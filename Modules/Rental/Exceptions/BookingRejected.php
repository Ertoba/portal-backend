<?php

namespace Modules\Rental\Exceptions;

use RuntimeException;

class BookingRejected extends RuntimeException
{
    public function __construct(private readonly array $rejection)
    {
        parent::__construct((string) ($rejection['message'] ?? ''));
    }

    public function rejection(): array
    {
        return $this->rejection;
    }
}
