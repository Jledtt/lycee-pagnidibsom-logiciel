<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeacherWorkSession extends Model
{
    protected $fillable = [
        'academic_year_id',
        'teacher_id',
        'school_class_id',
        'subject_id',
        'session_date',
        'starts_at',
        'ends_at',
        'hours_worked',
        'hourly_rate',
        'status',
        'teacher_signed_at',
        'validated_at',
        'validated_by',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'hours_worked' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'teacher_signed_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function feeLine(): HasOne
    {
        return $this->hasOne(TeacherFeeLine::class);
    }
}
