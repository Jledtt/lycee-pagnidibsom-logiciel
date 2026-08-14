<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    public const STATUS_GRADED = 'graded';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_SICK = 'sick';

    protected $fillable = [
        'assessment_id',
        'student_id',
        'score',
        'is_absent',
        'status',
        'comment',
        'entered_by',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'is_absent' => 'boolean',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_GRADED => 'Note saisie',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_DISPENSED => 'Dispense',
            self::STATUS_SICK => 'Malade',
        ];
    }

    public function resolvedStatus(): string
    {
        if ($this->status) {
            return $this->status;
        }

        return $this->is_absent ? self::STATUS_ABSENT : self::STATUS_GRADED;
    }

    public function isCounted(): bool
    {
        return $this->resolvedStatus() === self::STATUS_GRADED;
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
