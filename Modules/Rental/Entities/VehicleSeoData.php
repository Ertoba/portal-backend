<?php

namespace Modules\Rental\Entities;

use App\Traits\Model\HasStorageTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\CentralLogics\Helpers;

class VehicleSeoData extends Model
{
    use HasStorageTrait;

    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'meta_data' => 'array',
    ];



    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        return $this->storageFullUrl('vehicle_meta_data', 'image', $value);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    
    protected static function booted(): void
    {
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

}
