<?php

namespace Modules\Rental\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

trait VehicleTaxonomyTrait
{
    protected const TAXONOMY_COLUMNS = ['id', 'name', 'image', 'status', 'created_at'];

    protected function taxonomyList(Builder $query, array $filters, array $paginate): LengthAwarePaginator
    {
        return $query->withStorage()
            ->when($filters['active'] ?? false, fn ($builder) => $builder->where('status', 1))
            ->when($filters['search'] ?? null, fn ($builder, $search) => $builder->where(
                fn ($group) => $this->matchTaxonomyName($group, $search)
            ))
            ->select(self::TAXONOMY_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    private function matchTaxonomyName(mixed $group, string $search): void
    {
        foreach (array_filter(explode(' ', $search), fn ($token) => $token !== '') as $token) {
            $group->orWhere('name', 'like', "%{$token}%");
        }
    }
}
