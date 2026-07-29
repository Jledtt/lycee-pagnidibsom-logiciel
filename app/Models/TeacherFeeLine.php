<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherFeeLine extends Model
{
    protected $fillable = [
        'teacher_fee_statement_id',
        'teacher_work_session_id',
        'school_class_id',
        'subject_id',
        'description',
        'hours',
        'hourly_rate',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TeacherFeeStatement::class, 'teacher_fee_statement_id');
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(TeacherWorkSession::class, 'teacher_work_session_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
