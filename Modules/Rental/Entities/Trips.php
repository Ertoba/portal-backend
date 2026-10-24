<?php

namespace Modules\Rental\Entities;


use App\Models\OrderProDiscount;
use App\Traits\Item\MissingAddonRelationsTrait;
use App\Models\User;
use App\Models\Guest;
use App\Models\Module;
use App\Models\Store;
use App\Models\Zone;
use App\Models\CashBackHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\TaxModule\Entities\OrderTax;
use App\Traits\System\SidebarCountsTrait;

class Trips extends Model
{
    /**
     * The trip's distance, said in the unit the platform is configured for — port doc §3.4.
     *
     * `distance` is a MEASURED value and is kilometres always; only the presentation follows
     * `business_settings.distance_unit`. Same shape as Order::getDistanceLabelAttribute(), so a
     * trip and an order never disagree about what a distance means.
     */
    public function getDistanceLabelAttribute(): ?string
    {
        $kilometres = (float) ($this->attributes['distance'] ?? 0);

        return $kilometres > 0 ? app(\App\Services\System\DistanceService::class)->format($kilometres) : null;
    }

    use HasFactory, MissingAddonRelationsTrait, SidebarCountsTrait;

    protected $guarded = ['id'];
    protected $casts = [
        'user_id' => 'integer',
        'provider_id' => 'integer',
        'zone_id' => 'integer',
        'module_id' => 'integer',
        'cash_back_id' => 'integer',
        'trip_details_id' => 'integer',
        'trip_amount' => 'float',
        'discount_on_trip' => 'float',
        'coupon_discount_amount' => 'float',
        'tax_amount' => 'float',
        'tax_percentage' => 'float',
        'additional_charge' => 'float',
        'partially_paid_amount' => 'float',
        'distance' => 'float',
        'estimated_hours' => 'float',
        'ref_bonus_amount' => 'float',
        'is_guest' => 'integer',
        'edited' => 'integer',
        'checked' => 'integer',
        'scheduled' => 'integer',
        'quantity' => 'integer',
        'avg_rating' => 'float',
        'is_hidden' => 'integer',
    ];

    public function scopeNotHiddenForCustomer($query)
    {
        return $query->where($this->getTable() . '.is_hidden', 0);
    }

    public static function userCouponUsage($userId, $couponCode = null)
    {
        return self::query()
            ->where('user_id', $userId)
            ->whereNotNull('coupon_code')
            ->when($couponCode, fn ($query) => $query->where('coupon_code', $couponCode))
            ->selectRaw('coupon_code, COUNT(*) as total')
            ->groupBy('coupon_code')
            ->pluck('total', 'coupon_code');
    }

    public static function couponLimitReached($userId, $coupon)
    {
        if (!$userId || $coupon == null || $coupon->limit == null) {
            return false;
        }
        return (int) self::userCouponUsage(userId: $userId, couponCode: $coupon->code)->get($coupon->code, 0) >= $coupon->limit;
    }

    public function getPickupLocationAttribute($value)
    {
        if ($value) {
            return json_decode($value, true);
        }
        return $value;
    }
    public function getDestinationLocationAttribute($value)
    {
        if ($value) {
            return json_decode($value, true);
        }
        return $value;
    }
    public function getUserInfoAttribute($value)
    {
        if ($value) {
            return json_decode($value, true);
        }
        return $value;
    }

    public function cashback_history()
    {
        return $this->hasOne(CashBackHistory::class, 'trip_id');
    }
    public function provider()
    {
        return $this->belongsTo(Store::class,'provider_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
    public function trip_transaction()
    {
        return $this->hasOne(TripTransaction::class,'trip_id');
    }

    public function proDiscount()
    {
        return $this->hasOne(OrderProDiscount::class, 'trip_id');
    }

    public function trip_details()
    {
        return $this->hasMany(TripDetails::class,'trip_id');
    }

    public function vehicle_identity()
    {
        return $this->hasMany(TripVehicleDetails::class,'trip_id');
    }
    public function guest()
    {
        return $this->belongsTo(Guest::class, 'user_id','id');
    }
    /**
     * @return BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany
     */
    public function assignedVehicle(): HasMany
    {
        return $this->hasMany(TripVehicleDetails::class, 'trip_id')->whereNotNull('vehicle_identity_id');
    }
    public function partialPayment(): HasMany
    {
        return $this->hasMany(PartialPayment::class, 'trip_id');
    }

    /**
     * @return HasMany
     */
    public function assignedDriver(): HasMany
    {
        return $this->hasMany(TripVehicleDetails::class, 'trip_id')->whereNotNull('vehicle_driver_id');
    }

    public function scopeProviderTrip($query)
    {
        return $query->where('trip_type', 'completed');
    }


    public function scopeScheduled($query)
    {
        return $query->where('scheduled', 1);
    }
    public function scopeInstant($query)
    {
        return $query->where('scheduled', 0);
    }

    public function scopePending($query)
    {
        return $query->where('trip_status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('trip_status', 'confirmed');
    }

    public function scopeOngoing($query)
    {
        return $query->where('trip_status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('trip_status', 'completed');
    }

    public function scopeCanceled($query)
    {
        return $query->where('trip_status', 'canceled');
    }

    public function scopePaymentFailed($query)
    {
        return $query->where('trip_status', 'payment_failed');
    }

    public function statusBeforePaymentFailure(): string
    {
        $stamps = collect(['pending', 'confirmed', 'ongoing', 'completed'])
            ->mapWithKeys(fn ($status) => [$status => $this->getRawOriginal($status)])
            ->filter();

        return $stamps->isEmpty() ? 'pending' : (string) $stamps->sort()->keys()->last();
    }

    public static function statusCounts(?Builder $query = null): Collection
    {
        $grouped = ($query ? clone $query : static::query())
            ->selectRaw('trip_status, count(*) as aggregate')
            ->groupBy('trip_status');

        $key = md5($grouped->toSql() . serialize($grouped->getBindings()));

        static $cache = [];

        return $cache[$key] ??= $grouped->get()->pluck('aggregate', 'trip_status');
    }

    public function scopeZone($query, $id): void
    {
        $query->where('zone_id', '=', $id);
    }

    /**
     * Five report methods in the admin ReportController already call ->module($id), but the
     * scope was never defined -- so any of those reports reached with a module_id parameter
     * threw BadMethodCallException instead of filtering.
     */
    public function scopeModule($query, $id): void
    {
        $query->where('module_id', '=', $id);
    }

    public function vehicles()
    {
        return $this->hasManyThrough(Vehicle::class , TripDetails::class,'trip_id','id','id','vehicle_id');
    }
    public function orderTaxes()
    {
        if (! addon_published_status('TaxModule')) {
            return $this->missingAddonMorphMany();
        }

        return $this->morphMany(OrderTax::class, 'order');
    }

}
