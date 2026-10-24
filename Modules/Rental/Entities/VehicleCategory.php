<?php

namespace Modules\Rental\Entities;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;
class VehicleCategory extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;

    protected $guarded = ['id'];

    protected $fillable = [];

    protected $appends = ['image_full_url'];

    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('category', 'image', $this->image);
    }
    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }


    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

}
