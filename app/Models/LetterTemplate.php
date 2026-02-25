<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    protected $fillable = [
        'slug',
        'institution_category_id', // Updated
        'title',
        'description',
        'content',
        'icon',
        'color',
        'is_active'
    ];

    // Relationship: A template belongs to a specific Category
    public function category()
    {
        return $this->belongsTo(InstitutionCategory::class, 'institution_category_id');
    }
}
