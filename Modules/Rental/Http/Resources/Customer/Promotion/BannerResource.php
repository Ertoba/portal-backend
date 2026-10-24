<?php

namespace Modules\Rental\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class BannerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'type' => data_get($this->resource, 'type'),
            'link' => data_get($this->resource, 'link'),
            'provider_id' => data_get($this->resource, 'provider_id'),
            'image_full_url' => data_get($this->resource, 'image_full_url'),
        ]);
    }
}
