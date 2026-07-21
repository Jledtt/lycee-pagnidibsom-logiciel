<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockExamCandidate extends Model
{
    protected $fillable = [
        'mock_exam_id',
        'student_id',
        'school_class_id',
        'anonymous_code',
        'room_name',
        'status',
        'jury_decision',
        'jury_observation',
        'jury_decided_at',
        'jury_decided_by',
    ];

    protected $casts = [
        'jury_decided_at' => 'datetime',
    ];

    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(MockExamScore::class);
    }
}
