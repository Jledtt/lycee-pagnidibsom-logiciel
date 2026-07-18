<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequiredStudentDocument extends Model
{
    protected $fillable = [
        'name',
        'document_type',
        'scope',
        'level_cycle',
        'school_class_id',
        'status',
        'position',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function appliesTo(?SchoolClass $schoolClass): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->scope === 'all') {
            return true;
        }

        if (! $schoolClass) {
            return false;
        }

        if ($this->scope === 'class') {
            return (int) $this->school_class_id === (int) $schoolClass->id;
        }

        return $this->scope === 'cycle'
            && filled($this->level_cycle)
            && $this->level_cycle === $schoolClass->level?->cycle;
    }
}
