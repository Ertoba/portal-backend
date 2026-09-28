<?php

namespace Modules\Rental\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\CentralLogics\Helpers;

class VehicleSeoData extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $appends = ['image_full_url'];

    protected $casts = [
        'meta_data' => 'array',
    ];



    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        return Helpers::get_full_url('vehicle_meta_data', $value, 'public');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    
}
