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
        'fee_quantity',
        'fee_quantity_unit',
        'fee_rate',
        'fee_amount',
        'fee_withholding_amount',
        'fee_advance_amount',
        'fee_other_deduction_amount',
        'beneficiary_identity_type',
        'beneficiary_identity_number',
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
        'fee_quantity' => 'decimal:2',
        'fee_withholding_amount' => 'decimal:2',
        'fee_advance_amount' => 'decimal:2',
        'fee_other_deduction_amount' => 'decimal:2',
        'fee_paid_at' => 'datetime',
    ];

    /** @return BelongsTo<MockExam, $this> */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasMany<MockExamScore, $this> */
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

    public function calculatedFeeQuantity(): float
    {
        return (float) ($this->fee_quantity
            ?? $this->received_copies
            ?? $this->expected_copies
            ?? 0);
    }

    public function calculatedFeeGrossAmount(): float
    {
        if ($this->fee_amount !== null) {
            return (float) $this->fee_amount;
        }

        return round($this->calculatedFeeQuantity() * (float) ($this->fee_rate ?? 0), 2);
    }

    public function calculatedFeeNetAmount(): float
    {
        return max(0, round(
            $this->calculatedFeeGrossAmount()
            - (float) ($this->fee_withholding_amount ?? 0)
            - (float) ($this->fee_advance_amount ?? 0)
            - (float) ($this->fee_other_deduction_amount ?? 0),
            2,
        ));
    }
}
