<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = [
        'case_id',
        'timeline_id',
        'direction',
        'sender_email',
        'recipient_email',
        'subject',
        'body_text',
        'body_html',
        'message_id',
        'parent_id'
    ];

    public function case()
    {
        return $this->belongsTo(Cases::class, 'case_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'email_id');
    }
}
