<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function teacherWorkSessions(): HasMany
    {
        return $this->hasMany(TeacherWorkSession::class, 'teacher_id');
    }

    public function teacherFeeStatements(): HasMany
    {
        return $this->hasMany(TeacherFeeStatement::class, 'teacher_id');
    }

    public function teacherDocuments(): HasMany
    {
        return $this->hasMany(TeacherDocument::class, 'teacher_id');
    }

    public function teacherAvailabilitySchedules(): HasMany
    {
        return $this->hasMany(TeacherAvailabilitySchedule::class, 'teacher_id');
    }
}
