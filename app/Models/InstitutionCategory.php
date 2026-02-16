<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionCategory extends Model
{
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
