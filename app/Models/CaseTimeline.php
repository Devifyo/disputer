<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseTimeline extends Model
{
    protected $fillable = [
        'case_id',
        'type',
        'actor',
        'description',
        'metadata',
        'occurred_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime'
    ];

    public function case()
    {
        return $this->belongsTo(Cases::class, 'case_id');
    }

    public function email()
    {
        return $this->hasOne(Email::class, 'timeline_id');
    }
}
