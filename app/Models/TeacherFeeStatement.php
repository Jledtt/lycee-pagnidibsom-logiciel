<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherFeeStatement extends Model
{
    protected $fillable = [
        'reference',
        'academic_year_id',
        'teacher_id',
        'period_month',
        'beneficiary_name',
        'identity_document_type',
        'identity_document_number',
        'gross_amount',
        'withholding_tax_rate',
        'withholding_tax_amount',
        'advance_amount',
        'other_deduction_amount',
        'net_amount',
        'status',
        'approved_at',
        'approved_by',
        'paid_at',
        'paid_by',
        'payment_method',
        'payment_reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'gross_amount' => 'decimal:2',
            'withholding_tax_rate' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'other_deduction_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function lines(): HasMany
    {
        return $this->hasMany(TeacherFeeLine::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
