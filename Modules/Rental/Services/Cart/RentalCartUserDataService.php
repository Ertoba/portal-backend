<?php

namespace Modules\Rental\Services\Cart;

use Modules\Rental\Entities\RentalCartUserData;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;

class RentalCartUserDataService extends BaseService
{
    public function guestRowsExist(mixed $guestId): bool
    {
        return $this->ownerScopedQuery($guestId, 1)->exists();
    }

    public function deleteForUser(mixed $userId): void
    {
        $this->ownerScopedQuery($userId, 0)->delete();
    }

    public function transferGuestRows(mixed $guestId, mixed $userId): void
    {
        RentalCartUserData::where('user_id', $guestId)->update(['user_id' => $userId, 'is_guest' => 0]);
    }


    public function find(array $context): ?RentalCartUserData
    {
        return $this->ownerScopedQuery($context['user_id'], $context['is_guest'])->first();
    }

    public function findOrNew(array $context): RentalCartUserData
    {
        return $this->ownerScopedQuery($context['user_id'], $context['is_guest'])->firstOrNew();
    }

    public function delete(array $context): void
    {
        $this->ownerScopedQuery($context['user_id'], $context['is_guest'])->delete();
    }

    public function deleteForCustomer(mixed $userId): void
    {
        $this->ownerScopedQuery($userId, 0)->delete();
    }

    private function ownerScopedQuery(mixed $userId, mixed $isGuest): Builder
    {
        return RentalCartUserData::where('user_id', $userId)->where('is_guest', $isGuest);
    }

}
