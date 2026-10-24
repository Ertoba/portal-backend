<?php

namespace Modules\Rental\Http\Resources\Vendor\Vehicle;

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
            'zone_id' => $this->resource->zone_id,
            'provider_id' => $this->resource->provider_id,
            'brand_id' => $this->resource->brand_id,
            'category_id' => $this->resource->category_id,
            'model' => $this->resource->model,
            'type' => $this->resource->type,
            'engine_capacity' => $this->resource->engine_capacity,
            'engine_power' => $this->resource->engine_power,
            'seating_capacity' => $this->resource->seating_capacity,
            'air_condition' => $this->resource->air_condition,
            'fuel_type' => $this->resource->fuel_type,
            'transmission_type' => $this->resource->transmission_type,
            'multiple_vehicles' => $this->resource->multiple_vehicles,
            'trip_hourly' => $this->resource->trip_hourly,
            'trip_distance' => $this->resource->trip_distance,
            'trip_day_wise' => $this->resource->trip_day_wise,
            'day_wise_price' => $this->resource->day_wise_price,
            'hourly_price' => $this->resource->hourly_price,
            'distance_price' => $this->resource->distance_price,
            'discount_type' => $this->resource->discount_type,
            'discount_price' => $this->resource->discount_price,
            'tag' => $this->decodedTag(),
            'status' => $this->resource->status,
            'new_tag' => $this->resource->new_tag,
            'total_trip' => $this->resource->total_trip,
            'avg_rating' => $this->resource->avg_rating,
            'rating' => $this->resource->rating,
            'total_reviews' => $this->resource->total_reviews,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'thumbnail_full_url' => $this->resource->thumbnail_full_url,
            'images_full_url' => $this->resource->images_full_url,
            'documents_full_url' => $this->resource->documents_full_url,
            'vehicle_identities' => $this->whenLoaded('vehicleIdentities'),
            'provider' => $this->whenLoaded('provider'),
            'category' => $this->whenLoaded('category'),
            'brand' => $this->whenLoaded('brand'),
            'translations' => $this->whenLoaded('translations'),
            'seo_data' => $this->whenLoaded('seoData'),
        ]);
    }

    private function decodedTag(): mixed
    {
        $tag = $this->resource->tag;

        return is_array($tag) ? $tag : json_decode((string) $tag, true);
    }
}
