<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLine extends Model
{
    protected $fillable = [
        'payment_id',
        'fee_type_id',
        'fee_schedule_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<FeeType, $this> */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /** @return BelongsTo<FeeSchedule, $this> */
    public function feeSchedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class);
    }
}
