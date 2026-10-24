<?php

namespace Modules\Rental\Http\Resources\Customer\Vehicle;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class VehicleTaxonomyResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'image_full_url' => $this->resource->image_full_url,
        ]);
    }
}
