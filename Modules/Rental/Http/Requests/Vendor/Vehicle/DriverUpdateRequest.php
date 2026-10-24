<?php

namespace Modules\Rental\Http\Requests\Vendor\Vehicle;

class DriverUpdateRequest extends DriverStoreRequest
{
    protected function ignoredId(): string
    {
        return ',' . $this->route('id');
    }

    protected function imagePresence(): string
    {
        return 'nullable';
    }
}
