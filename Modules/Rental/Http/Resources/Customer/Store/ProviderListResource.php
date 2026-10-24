<?php

namespace Modules\Rental\Http\Resources\Customer\Store;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProviderListResource extends BaseResource
{
    public static function renderList(mixed $stores): array
    {
        return collect($stores)->map(fn ($store) => (new static($store))->toArray(request()))->values()->all();
    }

    public function toArray(Request $request): array
    {
        $store = $this->resource;

        return array_merge(parent::toArray($request), [
            'id' => (int) $store->id,
            'name' => $store->name,
            'address' => $store->address,
            'logo_full_url' => $store->logo_full_url,
            'cover_photo_full_url' => $store->cover_photo_full_url,
            'delivery_time' => $store->delivery_time,
            'distance' => $store->distance,
            'vehicles_count' => (int) ($store->vehicles_count ?? 0),
            'verified_seller' => Helpers::get_verified_seller_status($store, $store->storeConfig),
            'ad' => $store->ad,
            'discount' => $store->discount ? [
                'discount' => $store->discount->discount,
                'discount_type' => $store->discount->discount_type,
            ] : null,
        ]);
    }
}
