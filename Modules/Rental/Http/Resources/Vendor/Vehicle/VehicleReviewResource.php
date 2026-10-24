<?php

namespace Modules\Rental\Http\Resources\Vendor\Vehicle;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class VehicleReviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'provider_id' => $this->resource->provider_id,
            'module_id' => $this->resource->module_id,
            'user_id' => $this->resource->user_id,
            'trip_id' => $this->resource->trip_id,
            'vehicle_id' => $this->resource->vehicle_id,
            'vehicle_identity_id' => $this->resource->vehicle_identity_id,
            'rating' => $this->resource->rating,
            'comment' => $this->resource->comment,
            'attachment' => $this->decodedAttachment(),
            'attachment_full_url' => $this->resource->attachment_full_url,
            'status' => $this->resource->status,
            'reply' => $this->resource->reply,
            'review_id' => $this->resource->review_id,
            'replied_at' => $this->resource->replied_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'vehicle_name' => $this->resource->vehicle?->name,
            'vehicle_image' => $this->resource->vehicle?->image,
            'customer_name' => $this->resource->customer
                ? $this->resource->customer->f_name . ' ' . $this->resource->customer->l_name
                : null,
            'customer_phone' => $this->resource->customer?->phone,
            'vehicle_image_full_url' => $this->resource->vehicle?->image_full_url,
        ]);
    }

    private function decodedAttachment(): mixed
    {
        $attachment = $this->resource->attachment;

        return is_array($attachment) ? $attachment : json_decode((string) $attachment);
    }
}
