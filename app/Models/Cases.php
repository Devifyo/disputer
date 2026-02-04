<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\CaseStatus;
class Cases extends Model
{
   protected $fillable = [
        'user_id', 'institution_id', 'institution_name', 
        'case_reference_id', 'email_route_id', 
        'status', 'stage', 'current_workflow_step', 'next_action_at'
    ];

    protected $casts = [
        'status' => CaseStatus::class, // Casts string to Enum
        'next_action_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function timeline()
    {
        return $this->hasMany(CaseTimeline::class, 'case_id')->latest();
    }

    public function emails()
    {
        return $this->hasMany(Email::class, 'case_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'case_id');
    }

    public function getStatusColorAttribute()
    {
        return match($this->status?->value ?? $this->status) {
            'open', 'active' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            'pending', 'sent', 'waiting_reply' => 'bg-amber-50 text-amber-700 border-amber-100',
            'resolved', 'closed' => 'bg-slate-100 text-slate-600 border-slate-200',
            'escalated' => 'bg-purple-50 text-purple-700 border-purple-100',
            default => 'bg-blue-50 text-blue-700 border-blue-100'
        };
    }
    
    // Accessor for human readable Timeline Type
    public function getReadableTypeAttribute($value)
    {
        // Convert "case_created" to "Case Created"
        return ucwords(str_replace('_', ' ', $value)); 
    }
}
