<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    public const DISTINCTION_HIGH_HONORS_CONGRATULATIONS = 'T.H + Félicitations';

    public const DISTINCTION_HIGH_HONORS_ENCOURAGEMENT = 'T.H + Encouragements';

    public const DISTINCTION_HONOR_ROLL = 'Tableau d’honneur';

    public const DISTINCTION_WARNING_CONDUCT = 'Avertissement: - Conduite';

    public const DISTINCTION_WARNING_WORK = 'Avertissement: - Travail';

    public const DISTINCTION_BLAME_CONDUCT = 'Blâme: - Conduite';

    public const DISTINCTION_BLAME_WORK = 'Blâme: - Travail';

    protected $fillable = [
        'academic_year_id',
        'term_id',
        'student_id',
        'school_class_id',
        'general_average',
        'rank',
        'rank_is_tied',
        'class_size',
        'class_size_ranked',
        'class_size_unranked',
        'appreciation',
        'decision',
        'conduct',
        'distinction',
        'absence_hours',
        'principal_observation',
        'pdf_path',
        'status',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'general_average' => 'decimal:2',
        'rank_is_tied' => 'boolean',
        'absence_hours' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public static function distinctions(): array
    {
        return [
            self::DISTINCTION_HIGH_HONORS_CONGRATULATIONS,
            self::DISTINCTION_HIGH_HONORS_ENCOURAGEMENT,
            self::DISTINCTION_HONOR_ROLL,
            self::DISTINCTION_WARNING_CONDUCT,
            self::DISTINCTION_WARNING_WORK,
            self::DISTINCTION_BLAME_CONDUCT,
            self::DISTINCTION_BLAME_WORK,
        ];
    }

    public function getRankLabelAttribute(): ?string
    {
        if ($this->rank === null) {
            return null;
        }

        return $this->rank.'e'.($this->rank_is_tied ? ' ex æquo' : '');
    }

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
