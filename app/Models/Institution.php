<?php

namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{   
    use SoftDeletes;
    
   protected $fillable = [
        'name',
        'institution_category_id', // Updated FK
        'contact_email',
        'is_verified',
        'parent_id',
        'escalation_email',
        'escalation_contact_name'
    ];

    public function category()
    {
        return $this->belongsTo(InstitutionCategory::class, 'institution_category_id');
    }
    public function cases()
    {
        return $this->hasMany(Cases::class);
    }

    public function parent()
    {
        return $this->belongsTo(Institution::class, 'parent_id');
    }
}
