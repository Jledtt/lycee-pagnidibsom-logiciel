<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    protected $fillable = [
        'timetable_id',
        'sort_order',
        'period_label',
        'starts_at',
        'ends_at',
        'day_of_week',
        'subject_id',
        'subject_name',
        'teacher_name',
        'room',
        'is_break',
    ];

    protected $casts = [
        'is_break' => 'boolean',
    ];

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
