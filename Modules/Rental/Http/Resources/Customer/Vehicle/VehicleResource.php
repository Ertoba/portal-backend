<?php

namespace Modules\Rental\Http\Resources\Customer\Vehicle;

use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class VehicleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'provider_id' => $this->resource->provider_id,
            'type' => $this->resource->type,
            'engine_capacity' => $this->resource->engine_capacity,
            'seating_capacity' => $this->resource->seating_capacity,
            'air_condition' => $this->resource->air_condition,
            'fuel_type' => $this->resource->fuel_type,
            'transmission_type' => $this->resource->transmission_type,
            'trip_hourly' => $this->resource->trip_hourly,
            'trip_distance' => $this->resource->trip_distance,
            'trip_day_wise' => $this->resource->trip_day_wise,
            'day_wise_price' => $this->resource->day_wise_price,
            'hourly_price' => $this->resource->hourly_price,
            'distance_price' => $this->resource->distance_price,
            'discount_type' => $this->resource->discount_type,
            'discount_price' => $this->resource->discount_price,
            'status' => $this->resource->status,
            'new_tag' => $this->resource->new_tag,
            'avg_rating' => $this->resource->avg_rating,
            'total_reviews' => $this->resource->total_reviews,
            'thumbnail_full_url' => $this->resource->thumbnail_full_url,
            'images_full_url' => $this->resource->images_full_url,
            'total_vehicle_count' => $this->whenHas('total_vehicle_count'),
            'verified_seller' => Helpers::get_verified_seller_status($this->resource->provider, $this->resource->provider?->storeConfig),
            'provider' => $this->whenLoaded('provider', fn () => $this->resource->provider->append(Store::IMAGE_URL_APPENDS)),
        ]);
    }
}
