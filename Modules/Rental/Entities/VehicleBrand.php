<?php

namespace Modules\Rental\Entities;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;
class VehicleBrand extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;

    protected $guarded = ['id'];

    protected $appends = ['image_full_url'];

    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }




    public function vehicles():HasMany
    {
        return $this->hasMany(Vehicle::class, 'brand_id');
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('brand', 'image', $this->image);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

}
