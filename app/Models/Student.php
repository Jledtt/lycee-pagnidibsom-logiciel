<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'matricule',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'birth_place',
        'photo_path',
        'desired_class',
        'origin_school',
        'previous_class',
        'repeated_class',
        'address',
        'nationality',
        'ethnicity',
        'religion',
        'sector',
        'district',
        'home_phone',
        'health_notes',
        'health_conditions',
        'sport_aptitude',
        'emergency_contact_name',
        'emergency_contact_phone',
        'school_info_whatsapp',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'health_conditions' => 'array',
        'sport_aptitude' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relationship', 'is_primary', 'can_receive_sms', 'can_pickup_child'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'female' => 'Fille',
            'male' => 'Garcon',
            default => 'Non renseign?',
        };
    }

    public function getGenderShortLabelAttribute(): string
    {
        return match ($this->gender) {
            'female' => 'F',
            'male' => 'G',
            default => '-',
        };
    }
}
