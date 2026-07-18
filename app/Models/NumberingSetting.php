<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSetting extends Model
{
    protected $fillable = [
        'type',
        'label',
        'prefix',
        'format',
        'padding',
        'next_number',
        'status',
    ];
}
