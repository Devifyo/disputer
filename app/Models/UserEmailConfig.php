<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class UserEmailConfig extends Model
{
    protected $fillable = [
        'user_id', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
        'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_encryption',
        'from_name', 'from_email'
    ];

    // Automatically encrypt passwords when saving
    public function setSmtpPasswordAttribute($value)
    {
        $this->attributes['smtp_password'] = Crypt::encryptString($value);
    }

    // Automatically decrypt passwords when accessing
    public function getSmtpPasswordAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
