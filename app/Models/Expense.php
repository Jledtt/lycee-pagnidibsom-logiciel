<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'academic_year_id',
        'teacher_fee_statement_id',
        'spent_at',
        'category',
        'beneficiary',
        'payment_method',
        'amount',
        'proof_reference',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'created_by',
        'cancelled_by',
    ];

    protected $casts = [
        'spent_at' => 'date',
        'amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacherFeeStatement(): BelongsTo
    {
        return $this->belongsTo(TeacherFeeStatement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
