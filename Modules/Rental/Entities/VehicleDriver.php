<?php

namespace Modules\Rental\Entities;

use App\CentralLogics\Helpers;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationRelationTrait;

class VehicleDriver extends Model
{
    use HasFactory, HasStorageTrait, HasTranslationRelationTrait;

    protected $guarded = ['id'];
    protected $fillable = [];

    /**
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * @param $query
     * @param $status
     * @return void
     */
    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    /**
     * @return BelongsTo
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'provider_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function trips(): HasMany
    {
        return $this->hasMany(TripVehicleDetails::class, 'vehicle_driver_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function ongoingTrips(): HasMany
    {
        return $this->trips()->whereHas('trip', function ($query) {
            $query->where('trip_status', 'ongoing');
        });
    }

    /**
     * @return HasMany
     */
    public function completedTrips(): HasMany
    {
        return $this->trips()->whereHas('trip', function ($query) {
            $query->where('trip_status', 'completed');
        });
    }

    /**
     * @return HasMany
     */
    public function canceledTrips(): HasMany
    {
        return $this->trips()->whereHas('trip', function ($query) {
            $query->where('trip_status', 'canceled');
        });
    }


    /**
     * @return MorphMany
     */
    /**
     * @param $string
     * @return bool
     */
    private function isValidJson($string): bool
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * @return MorphMany
     */
    /**
     * @return mixed|string|null
     */
    public function getImageFullUrlAttribute(): mixed
    {
        return $this->storageFullUrl('driver', 'image', $this->image);
    }

    /**
     * @return array
     */
    public function getIdentityImageFullUrlAttribute(): array
    {
        $images = [];
        $value = is_array($this->identity_image)
            ? $this->identity_image
            : ($this->identity_image && is_string($this->identity_image) && $this->isValidJson($this->identity_image)
                ? json_decode($this->identity_image, true)
                : []);
        if ($value){
            foreach ($value as $item){
                $item = is_array($item)?$item:(is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true):['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('driver',$item['img'],$item['storage']);
            }
        }

        return $images;
    }


    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

}
