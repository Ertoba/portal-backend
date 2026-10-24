<?php

namespace Modules\Rental\Http\Resources\Customer\Trip;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TripSummaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'trip_amount' => $this->resource->trip_amount,
            'created_at' => $this->resource->created_at,
            'estimated_hours' => $this->resource->estimated_hours,
            'provider' => $this->whenLoaded('provider', fn () => $this->withPicture(
                $this->resource->provider, 'logo_full_url'
            )),
            'trip_details' => $this->whenLoaded('trip_details', fn () => $this->resource->trip_details
                ->each(fn ($detail) => $this->withPicture($detail->vehicle, 'thumbnail_full_url'))),
        ]);
    }

    /**
     * A related model with its picture URL on it.
     *
     * These relations are handed to the client as models rather than through resources of their
     * own, so what ships is whatever the model serialises -- and `logo_full_url` /
     * `thumbnail_full_url` are accessors, not columns, absent from either model's $appends. The
     * payload carried the bare `logo`/`thumbnail` filename and nothing that resolved it. Appended
     * per instance rather than on the models, which would put a storage lookup on every store and
     * vehicle payload in the app to fix a key only this one is short of.
     *
     * TripService::getLastList() already eager-loads the `storage` each accessor reads, so this
     * resolves off loaded data and issues no query (rule 3).
     */
    private function withPicture(mixed $model, string $attribute): mixed
    {
        return $model?->append($attribute);
    }
}
