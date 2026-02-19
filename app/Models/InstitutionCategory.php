<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstitutionCategory extends Model
{   
    use SoftDeletes;
    protected $fillable = ['name', 'slug', 'workflow_config', 'is_verified', 'fallback_escalation_email'];

    protected $casts = [
        'workflow_config' => 'array',
        'is_verified' => 'boolean',
    ];
    public function institutions()
    {
        return $this->hasMany(Institution::class);
    }
}
