<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    public function institutions()
    {
        return $this->hasMany(Institution::class);
    }
}
