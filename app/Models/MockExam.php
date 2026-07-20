<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockExam extends Model
{
    protected $fillable = [
        'academic_year_id',
        'name',
        'exam_type',
        'starts_on',
        'ends_on',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'mock_exam_classes')
            ->withTimestamps();
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(MockExamSubject::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(MockExamCandidate::class);
    }

    public function getExamTypeLabelAttribute(): string
    {
        return match ($this->exam_type) {
            'bac_blanc' => 'BAC blanc',
            default => 'BEPC blanc',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_progress' => 'En cours',
            'finished' => 'Termine',
            'archived' => 'Archive',
            default => 'Preparation',
        };
    }
}
