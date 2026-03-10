<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuccessStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'email',
        'story',
        'media_files',
        'is_published',
    ];

    // Automatically cast the JSON column to a PHP array
    protected $casts = [
        'media_files' => 'array',
        'is_published' => 'boolean',
    ];

    // Relationship to the user (if authenticated)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}