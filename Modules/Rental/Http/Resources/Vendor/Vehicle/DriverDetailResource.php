<?php

namespace Modules\Rental\Http\Resources\Vendor\Vehicle;

use Illuminate\Http\Request;

class DriverDetailResource extends DriverResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'trips' => $this->whenLoaded('trips'),
        ]);
    }
}
