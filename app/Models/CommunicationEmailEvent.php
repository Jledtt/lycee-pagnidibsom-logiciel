<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationEmailEvent extends Model
{
    protected $fillable = [
        'communication_message_id',
        'svix_id',
        'provider_message_id',
        'event_type',
        'event_at',
        'recipient_email',
        'reason',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunicationMessage::class, 'communication_message_id');
    }
}
