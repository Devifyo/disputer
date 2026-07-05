<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimEvent extends Model
{
    protected $fillable = ['claim_id', 'label', 'status', 'happened_at', 'sort'];

    protected $casts = ['happened_at' => 'datetime'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
