<?php

namespace App\Services;

class MiliEntitlementService
{
    public function enabled(?string $feature = null): bool
    {
        // Routes without a feature name are allowed.
        if ($feature === null || $feature === '') {
            return true;
        }

        return (bool) config("mili.features.{$feature}", false);
    }
}
