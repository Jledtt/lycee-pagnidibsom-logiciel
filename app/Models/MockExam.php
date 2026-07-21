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
        'term_id',
        'name',
        'exam_type',
        'starts_on',
        'ends_on',
        'status',
        'result_status',
        'validated_at',
        'validated_by',
        'finalized_at',
        'finalized_by',
        'locked_at',
        'locked_by',
        'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'validated_at' => 'datetime',
        'finalized_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
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
            'trimestriel' => 'Examen trimestriel',
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

    public function getResultStatusLabelAttribute(): string
    {
        return match ($this->result_status) {
            'provisoire' => 'Provisoire',
            'corrige' => 'Corrige',
            'definitif' => 'Definitif',
            'verrouille' => 'Verrouille',
            default => 'Preparation',
        };
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->result_status === 'verrouille' || filled($this->locked_at);
    }
}
