<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    protected $fillable = [
        'timetable_id',
        'generation_run_id',
        'timetable_period_id',
        'sort_order',
        'period_label',
        'starts_at',
        'ends_at',
        'day_of_week',
        'class_subject_id',
        'subject_id',
        'teacher_id',
        'subject_name',
        'teacher_name',
        'room',
        'is_break',
        'is_locked',
        'source',
    ];

    protected $casts = [
        'is_break' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(TimetableGenerationRun::class, 'generation_run_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'timetable_period_id');
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
