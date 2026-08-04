<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PREFERRED = 'preferred';

    protected $fillable = [
        'teacher_availability_schedule_id',
        'timetable_period_id',
        'day_of_week',
        'status',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TeacherAvailabilitySchedule::class, 'teacher_availability_schedule_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'timetable_period_id');
    }

    public static function labels(): array
    {
        return [
            self::STATUS_UNAVAILABLE => 'Indisponible',
            self::STATUS_AVAILABLE => 'Disponible',
            self::STATUS_PREFERRED => 'Préféré',
        ];
    }
}
