<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationMessage extends Model
{
    protected $fillable = [
        'campaign_id',
        'template_code',
        'event_type',
        'related_type',
        'related_id',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'recipient_email',
        'subject',
        'body',
        'channel',
        'status',
        'attempts',
        'provider_message_id',
        'delivery_status',
        'delivery_status_at',
        'delivery_error',
        'delivered_at',
        'bounced_at',
        'complained_at',
        'error_message',
        'metadata',
        'deduplication_key',
        'created_by',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'delivery_status_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function emailEvents(): HasMany
    {
        return $this->hasMany(CommunicationEmailEvent::class, 'communication_message_id');
    }
}
