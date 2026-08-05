<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableGenerationRun extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'academic_year_id',
        'status',
        'solver_status',
        'input_snapshot',
        'result',
        'diagnostics',
        'requested_by',
        'applied_by',
        'applied_at',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'result' => 'array',
        'diagnostics' => 'array',
        'applied_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function canBeApplied(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && in_array($this->solver_status, ['OPTIMAL', 'FEASIBLE'], true)
            && filled($this->result['assignments'] ?? null);
    }
}
