<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExitAuthorization extends Model
{
    protected $fillable = [
        'academic_year_id',
        'student_id',
        'school_class_id',
        'document_date',
        'departure_at',
        'return_at',
        'subject_name',
        'destination',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'departure_at' => 'datetime',
        'return_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
