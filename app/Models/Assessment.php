<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    public const ENTRY_MODE_STANDARD = 'standard';

    public const ENTRY_MODE_WRITTEN = 'written';

    public const ENTRY_MODE_ORAL_SPORT = 'oral_sport';

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
        'entry_mode',
        'is_locked',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'assessment_date' => 'date',
        'is_locked' => 'boolean',
    ];

    public static function entryModeLabels(): array
    {
        return [
            self::ENTRY_MODE_STANDARD => 'Standard',
            self::ENTRY_MODE_WRITTEN => 'Ecrit / anonymat',
            self::ENTRY_MODE_ORAL_SPORT => 'Oral / sport',
        ];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<TermPeriod, $this> */
    public function termPeriod(): BelongsTo
    {
        return $this->belongsTo(TermPeriod::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<AssessmentType, $this> */
    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
