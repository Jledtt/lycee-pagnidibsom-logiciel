<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_required',
        'status',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(FeeSchedule::class);
    }

    public function paymentLines(): HasMany
    {
        return $this->hasMany(PaymentLine::class);
    }
}
