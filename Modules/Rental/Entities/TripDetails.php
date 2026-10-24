<?php

namespace Modules\Rental\Entities;

use App\Models\User;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripDetails extends Model
{
    /**
     * This line's distance, said in the unit the platform is configured for — port doc §3.4.
     *
     * `distance` is MEASURED kilometres; only the presentation follows `distance_unit`. The same
     * accessor as Trips and Order, so an invoice line and its trip never disagree about the unit.
     */
    public function getDistanceLabelAttribute(): ?string
    {
        $kilometres = (float) ($this->attributes['distance'] ?? 0);

        return $kilometres > 0 ? app(\App\Services\System\DistanceService::class)->format($kilometres) : null;
    }

    use HasFactory, ReportFilterTrait;

    protected $guarded = ['id'];
    protected $casts = [
        'trip_id' => 'integer',
        'vehicle_id' => 'integer',
        'quantity' => 'integer',
        'scheduled' => 'integer',
        'price' => 'float',
        'discount_on_trip' => 'float',
        'tax_amount' => 'float',
        'estimated_hours' => 'float',
        'distance' => 'float',
        'calculated_price' => 'float',
    ];

    public function getVehicleDetailsAttribute($value)
    {
        if ($value) {
            return json_decode($value, true);
        }
        return $value;
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trips::class, 'trip_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
    public function vehicleVariations(): HasMany
    {
        return $this->hasMany(VehicleIdentity::class,'vehicle_id','vehicle_id');
    }

    public function tripVehicleDetails(): HasMany
    {
        return $this->hasMany(TripVehicleDetails::class);
    }



}
