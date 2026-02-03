<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cases extends Model
{
   protected $fillable = [
        'user_id',
        'institution_id',
        'institution_name',
        'case_reference_id',
        'email_route_id',
        'status',
        'stage'
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
