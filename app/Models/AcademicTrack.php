<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTrack extends Model
{
    protected $fillable = [
        'name',
        'code',
        'kind',
        'description',
        'status',
    ];

    /** @return HasMany<SchoolClass, $this> */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
