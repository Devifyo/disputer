<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'cases_allowed',
        'cases_used',
        'status',
        'transaction_id',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'cases_allowed' => 'integer',
        'cases_used' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Check if the user can still submit a case under this subscription
    public function isValid()
    {
        if ($this->status !== 'active') {
            return false;
        }

        // If there's an expiration date, check if it's passed
        if ($this->expires_at && now()->greaterThan($this->expires_at)) {
            return false;
        }

        // If there is a case limit, check if they've hit it
        if ($this->cases_allowed !== null && $this->cases_used >= $this->cases_allowed) {
            return false;
        }

        return true;
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}