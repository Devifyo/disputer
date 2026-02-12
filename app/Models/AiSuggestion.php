<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSuggestion extends Model
{
    protected $fillable = [
        'case_id',
        'attachment_id',
        'type',
        'confidence_score',
        'suggested_data',
        'reasoning',
        'status',
        'acted_at'
    ];

    protected $casts = [
        'suggested_data' => 'array', // Automatically converts JSON to Array
        'acted_at' => 'datetime',
    ];

    // Relationship to the Case
    public function case()
    {
        return $this->belongsTo(Cases::class, 'case_id');
    }

    // Relationship to the specific file (if applicable)
    public function attachment()
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }
    
    // Helper to check if this is waiting for user input
    public function isPending()
    {
        return $this->status === 'pending';
    }
}
