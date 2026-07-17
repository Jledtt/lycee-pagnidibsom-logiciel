<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryRecord extends Model
{
    protected $fillable = [
        'academic_year_id',
        'student_id',
        'school_class_id',
        'type',
        'title',
        'description',
        'record_date',
        'created_by',
    ];

    protected $casts = [
        'record_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
