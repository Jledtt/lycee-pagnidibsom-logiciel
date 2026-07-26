<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    protected $fillable = [
        'type',
        'audience',
        'school_class_id',
        'role_name',
        'title',
        'subject',
        'body',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'created_by',
        'queued_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'campaign_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
