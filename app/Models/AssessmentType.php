<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentType extends Model
{
    public const NAME_DEVOIR = 'Devoir';

    public const NAME_COMPOSITION = 'Composition';

    protected $fillable = [
        'name',
        'weight',
        'status',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
