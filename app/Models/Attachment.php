<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class Attachment extends Model
{
    protected $fillable = [
        'case_id',
        'email_id',
        'file_path',
        'file_name',
        'mime_type',
        'ai_analysis_status'
    ];

    public function case()
    {
        return $this->belongsTo(Cases::class, 'case_id');
    }

    public function email()
    {
        return $this->belongsTo(Email::class, 'email_id');
    }

    public function getPublicLinkAttribute()
    {
        // OLD: return url(Storage::url($this->file_path));
        
        // NEW: Return the branded viewer URL
        return route('user.evidence.view', ['attachment' => encrypt_id($this->id)]);
    }

    public function getSecureUrlAttribute()
    {
        // Generates a link valid for 60 minutes
        return \URL::temporarySignedRoute(
            'user.evidence.download', 
            now()->addMinutes(60),
            ['attachment' => encrypt_id($this->id)]
        );
    }
}
