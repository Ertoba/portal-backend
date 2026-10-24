<?php

namespace Modules\Rental\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class StoreBannerResource extends BaseResource
{
    private const TYPE = 'default';

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'type' => self::TYPE,
            'link' => $this->resource->default_link,
            'image_full_url' => $this->resource->image_full_url,
        ]);
    }
}
