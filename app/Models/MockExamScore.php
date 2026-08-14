<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockExamScore extends Model
{
    protected $fillable = [
        'mock_exam_subject_id',
        'mock_exam_candidate_id',
        'score',
        'is_absent',
        'observation',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'is_absent' => 'boolean',
    ];

    /** @return BelongsTo<MockExamSubject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(MockExamSubject::class, 'mock_exam_subject_id');
    }

    /** @return BelongsTo<MockExamCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(MockExamCandidate::class, 'mock_exam_candidate_id');
    }
}
