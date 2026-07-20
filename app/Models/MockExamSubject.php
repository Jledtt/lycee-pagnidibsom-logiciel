<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockExamSubject extends Model
{
    protected $fillable = [
        'mock_exam_id',
        'subject_id',
        'exam_part',
        'max_score',
        'coefficient',
        'position',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'coefficient' => 'decimal:2',
    ];

    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(MockExamScore::class);
    }

    public function getExamPartLabelAttribute(): string
    {
        return match ($this->exam_part) {
            'oral' => 'Oral',
            'sport' => 'Sport',
            default => 'Ecrit',
        };
    }
}
