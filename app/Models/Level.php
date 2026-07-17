<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    protected $fillable = [
        'name',
        'cycle',
        'position',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
