<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = [
        'academic_year_id',
        'term_id',
        'term_period_id',
        'school_class_id',
        'subject_id',
        'assessment_type_id',
        'teacher_id',
        'title',
        'max_score',
        'assessment_date',
        'is_locked',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'assessment_date' => 'date',
        'is_locked' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function termPeriod(): BelongsTo
    {
        return $this->belongsTo(TermPeriod::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
