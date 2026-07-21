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
        'exam_date',
        'starts_at',
        'ends_at',
        'supervisor_one',
        'supervisor_two',
        'expected_copies',
        'received_copies',
        'absent_count',
        'incident_notes',
        'copies_received_at',
        'copy_receiver_name',
        'correction_teacher_name',
        'fee_rate',
        'fee_amount',
        'fee_status',
        'fee_paid_at',
        'fee_payment_reference',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'coefficient' => 'decimal:2',
        'exam_date' => 'date',
        'copies_received_at' => 'datetime',
        'fee_rate' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'fee_paid_at' => 'datetime',
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
