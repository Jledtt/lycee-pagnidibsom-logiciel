<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    protected $fillable = [
        'academic_year_id',
        'term_id',
        'student_id',
        'school_class_id',
        'general_average',
        'rank',
        'class_size',
        'appreciation',
        'pdf_path',
        'status',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'general_average' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
