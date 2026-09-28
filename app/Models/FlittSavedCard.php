<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlittSavedCard extends Model
{
    protected $fillable = [
        'customer_id',
        'gateway',
        'rectoken',
        'masked_card',
        'card_type',
        'card_bin',
        'rectoken_lifetime',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
