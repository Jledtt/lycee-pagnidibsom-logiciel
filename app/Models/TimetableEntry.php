<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    protected $fillable = [
        'academic_year_id',
        'school_class_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'subject_label',
        'teacher_name',
        'room',
        'notes',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function getDayLabelAttribute(): string
    {
        return self::dayLabels()[$this->day_of_week] ?? 'Jour';
    }

    public function getTimeLabelAttribute(): string
    {
        return substr((string) $this->starts_at, 0, 5).' - '.substr((string) $this->ends_at, 0, 5);
    }

    public static function dayLabels(): array
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
        ];
    }
}
