<?php

namespace Modules\Rental\Services\Trip;

use App\Services\BaseService;
use Modules\Rental\Entities\TripDetails;

class TripDetailsService extends BaseService
{
    public function insertMany(array $rows): void
    {
        TripDetails::insert($rows);
    }
}
