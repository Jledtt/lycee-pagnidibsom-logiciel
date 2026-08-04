<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherAvailabilitySchedule extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VALIDATED = 'validated';

    protected $fillable = [
        'academic_year_id',
        'teacher_id',
        'status',
        'notes',
        'source',
        'submitted_at',
        'validated_at',
        'updated_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    public static function labels(): array
    {
        return [
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_SUBMITTED => 'Transmise',
            self::STATUS_VALIDATED => 'Validée',
        ];
    }
}
