<?php

namespace Modules\Rental\Http\Resources\Customer\Vehicle;

use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Traits\Store\StoreDataTrait;
use Illuminate\Http\Request;

class VehicleDetailResource extends VehicleResource
{
    use StoreDataTrait;

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'total_vehicles' => $this->whenHas('total_vehicles'),
            'brand' => $this->whenLoaded('brand'),
            'category' => $this->whenLoaded('category'),
            'provider' => $this->providerBlock(),
            'meta_title' => $this->resource->seoData?->title,
            'meta_description' => $this->resource->seoData?->description,
            'meta_image' => $this->resource->seoData?->imageFullUrl,
            'meta_data' => $this->resource->seoData?->meta_data,
        ]);
    }

    private function providerBlock(): mixed
    {
        $provider = $this->resource->provider;

        if (! $provider) {
            return null;
        }

        $ratings = $this->calculateRating($provider->rating);

        return array_merge($provider->append(Store::IMAGE_URL_APPENDS)->toArray(), [
            'avg_rating' => $ratings['rating'],
            'rating_count' => $ratings['total'],
            'verified_seller' => Helpers::get_verified_seller_status($provider, $provider->storeConfig),
        ]);
    }
}
