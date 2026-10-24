<?php

namespace Modules\Rental\Services\Vehicle;

use App\Services\BaseService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ItemRatingTrait;
use App\Traits\Store\StoreDataTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Support\Storage\FileStorage;
use Modules\Rental\Entities\VehicleReview;
use App\Services\Store\StoreService;
use Modules\Rental\Services\Vehicle\VehicleService;

class VehicleReviewService extends BaseService
{
    use PersonalizationTrait, ItemRatingTrait, StoreDataTrait;

    private const ATTACHMENT_DIR = 'review';

    public function getProviderList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownerList('provider_id', $filters['provider_id'] ?? null, $filters, $paginate);
    }
    public function alreadyReviewed(array $keys): bool
    {
        return VehicleReview::where('vehicle_id', $keys['vehicle_id'] ?? null)
            ->where('user_id', $keys['user_id'] ?? null)
            ->where('trip_id', $keys['trip_id'] ?? null)
            ->where('vehicle_identity_id', $keys['vehicle_identity_id'] ?? null)
            ->exists();
    }
    public function submit(array $data): VehicleReview
    {
        $attachments = $this->storeAttachments($data['attachment_files'] ?? []);

        return DB::transaction(function () use ($data, $attachments) {
            $review = new VehicleReview();
            $review->user_id = $data['user_id'];
            $review->provider_id = $data['provider_id'];
            $review->module_id = $data['module_id'];
            $review->trip_id = $data['trip_id'];
            $review->vehicle_id = $data['vehicle_id'];
            $review->vehicle_identity_id = $data['vehicle_identity_id'];
            $review->comment = $data['comment'];
            $review->rating = $data['rating'];
            $review->attachment = json_encode($attachments);
            $review->save();

            $this->applyProviderRating($review->provider_id, $data['rating']);
            $this->applyVehicleRating($data['vehicle_id'], $data['rating']);
            $this->recordVehicleAction((int) $data['user_id'], (int) $data['vehicle_id'], 'review', $data['module_id']);

            return $review;
        });
    }
    public function getVehicleList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownerList('vehicle_id', $filters['vehicle_id'] ?? null, $filters, $paginate);
    }
    public function findReplyTarget(mixed $id, mixed $providerId): ?VehicleReview
    {
        return VehicleReview::where('provider_id', $providerId)->find($id);
    }
    public function updateReply(VehicleReview $review, ?string $reply): VehicleReview
    {
        $review->reply = $reply;
        $review->replied_at = now();
        $review->save();

        return $review;
    }
    public function findForIdentityAndTrip(mixed $vehicleIdentityId, mixed $tripId): ?VehicleReview
    {
        return VehicleReview::where([
            'vehicle_identity_id' => $vehicleIdentityId,
            'trip_id' => $tripId,
        ])->first();
    }
    private function ownerList(string $ownerColumn, mixed $ownerId, array $filters, array $paginate): LengthAwarePaginator
    {
        return VehicleReview::with($this->relations())
            ->where($ownerColumn, $ownerId)
            ->when($filters['search'] ?? null, fn ($query, $search) => $this->applySearch($query, $search))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    private function storeAttachments(array $files): array
    {
        return FileStorage::uploadAttachments(self::ATTACHMENT_DIR, array_filter($files));
    }
    private function applyProviderRating(mixed $providerId, int $rating): void
    {
        $store = app(StoreService::class)->find($providerId);

        if (! $store) {
            return;
        }

        $store->rating = $this->updateRating($store->rating, $rating);
        $store->save();
    }
    private function applyVehicleRating(mixed $vehicleId, int $rating): void
    {
        $vehicle = app(VehicleService::class)->find($vehicleId);

        if (! $vehicle) {
            return;
        }

        $vehicle->rating = self::updateRatingHistogram($vehicle->rating, $rating);
        $vehicle->avg_rating = self::averageRating(json_decode($vehicle->rating, true));
        $vehicle->save();
        $vehicle->increment('total_reviews');
    }
    private function relations(): array
    {
        return [
            'customer' => fn ($query) => $query->select('id', 'f_name', 'l_name')->withStorage(),
            'vehicle' => fn ($query) => $query->select('id', 'name', 'thumbnail')->withStorage(),
        ];
    }
    private function applySearch(mixed $query, string $search): mixed
    {
        $keywords = array_filter(explode(' ', $search));

        return $query->where(function ($builder) use ($keywords, $search) {
            $builder->whereHas('vehicle', function ($vehicle) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $vehicle->where('name', 'like', "%{$keyword}%");
                }
            })->orWhereHas('customer', function ($customer) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $customer->where('f_name', 'like', "%{$keyword}%")->orWhere('l_name', 'like', "%{$keyword}%");
                }
            })->orWhere('rating', $search)->orWhere('review_id', $search);
        });
    }
}
