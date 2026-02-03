<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
