<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    protected $fillable = [
        'school_name',
        'address',
        'phone',
        'email',
        'logo_path',
        'currency',
        'principal_name',
    ];
}
