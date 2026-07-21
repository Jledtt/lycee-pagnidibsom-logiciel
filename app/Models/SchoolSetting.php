<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    protected $fillable = [
        'school_name',
        'short_name',
        'address',
        'phone',
        'email',
        'website',
        'logo_path',
        'motto',
        'country',
        'national_motto',
        'city',
        'postal_box',
        'currency',
        'principal_name',
        'principal_title',
        'accountant_name',
    ];
}
