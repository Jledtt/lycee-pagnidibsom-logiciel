<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'subject',
        'body',
        'available_variables',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'available_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
