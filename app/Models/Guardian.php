<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property-read Pivot|null $pivot */
class Guardian extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_primary',
        'phone_secondary',
        'email',
        'address',
        'profession',
        'service',
        'status',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot(['relationship', 'is_primary', 'can_receive_sms', 'can_pickup_child'])
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
