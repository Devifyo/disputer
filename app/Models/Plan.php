<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
   protected $fillable = [
        'name',
        'slug',
        'type',
        'case_limit',
        'price',
        'currency',
        'payment_gateway_id',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'case_limit' => 'integer', 
        'features' => 'array',
        'is_active' => 'boolean',
    ];
}
