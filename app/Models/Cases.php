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
}
