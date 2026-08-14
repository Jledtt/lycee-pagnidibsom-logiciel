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
        'cancelled_by',
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

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<PaymentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentLine::class);
    }

    public function cancellationReasonForDisplay(): ?string
    {
        return $this->legacyCancellationParts()['reason'];
    }

    public function cancellationActorName(): ?string
    {
        return $this->canceller?->name ?? $this->legacyCancellationParts()['actor'];
    }

    public function isBackdated(): bool
    {
        return ! $this->paid_at->isSameDay($this->created_at);
    }

    public function backdatedEntryLabel(): ?string
    {
        return $this->isBackdated() ? 'saisi le '.$this->created_at->format('d/m') : null;
    }

    /**
     * @return array{reason: ?string, actor: ?string}
     */
    private function legacyCancellationParts(): array
    {
        $reason = $this->cancellation_reason;

        if (! is_string($reason) || $reason === '') {
            return ['reason' => null, 'actor' => null];
        }

        if (preg_match('/^(.*?)\s*\|\s*Annule par:\s*(.+)$/u', $reason, $matches) === 1) {
            return [
                'reason' => trim($matches[1]),
                'actor' => trim($matches[2]),
            ];
        }

        return ['reason' => $reason, 'actor' => null];
    }
}
