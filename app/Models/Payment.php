<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'receipt_number',
        'academic_year_id',
        'student_id',
        'enrollment_id',
        'paid_at',
        'amount',
        'payment_method',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentLine::class);
    }
}
