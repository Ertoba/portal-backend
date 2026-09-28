<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'item_id',
        'store_id',
    ];
    protected $casts = [
        'item_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
