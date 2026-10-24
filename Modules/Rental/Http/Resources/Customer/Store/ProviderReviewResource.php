<?php

namespace Modules\Rental\Http\Resources\Customer\Store;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProviderReviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $vehicle = $review->relationLoaded('vehicle') ? $review->getRelation('vehicle') : null;
        $customer = $review->relationLoaded('customer') ? $review->getRelation('customer') : null;

        return array_merge(parent::toArray($request), [
            'vehicle_id' => $review->vehicle_id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'reply' => $review->reply,
            'created_at' => $review->created_at,
            'updated_at' => $review->updated_at,
            'vehicle_name' => $vehicle?->name,
            'customer_name' => $customer ? trim($customer->f_name . ' ' . $customer->l_name) : null,
            'vehicle_image_full_url' => $vehicle?->thumbnail_full_url,
        ]);
    }
}
