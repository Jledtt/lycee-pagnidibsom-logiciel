<?php

namespace App\Services;

use App\Models\CommunicationEmailEvent;
use App\Models\CommunicationMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResendWebhookService
{
    public const TRACKED_EVENTS = [
        'email.sent',
        'email.delivered',
        'email.delivery_delayed',
        'email.bounced',
        'email.complained',
        'email.failed',
        'email.suppressed',
    ];

    public function record(array $payload, string $svixId): bool
    {
        $data = $payload['data'];
        $eventAt = Carbon::parse($payload['created_at'])->utc();
        $providerMessageId = (string) $data['email_id'];
        $reason = $this->reason($payload['type'], $data);

        return DB::transaction(function () use ($payload, $svixId, $eventAt, $providerMessageId, $reason): bool {
            $message = CommunicationMessage::query()
                ->where('provider_message_id', $providerMessageId)
                ->first();

            $inserted = DB::table('communication_email_events')->insertOrIgnore([
                'communication_message_id' => $message?->id,
                'svix_id' => $svixId,
                'provider_message_id' => $providerMessageId,
                'event_type' => $payload['type'],
                'event_at' => $eventAt,
                'recipient_email' => $payload['data']['to'][0] ?? null,
                'reason' => $reason,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            if ($message) {
                $this->applyLatestStatus($message);
            }

            return true;
        });
    }

    public function reconcileMessage(CommunicationMessage $message): void
    {
        if (! $message->provider_message_id) {
            return;
        }

        CommunicationEmailEvent::query()
            ->whereNull('communication_message_id')
            ->where('provider_message_id', $message->provider_message_id)
            ->update(['communication_message_id' => $message->id]);

        $this->applyLatestStatus($message);
    }

    private function applyLatestStatus(CommunicationMessage $message): void
    {
        $events = $message->emailEvents()
            ->whereIn('event_type', self::TRACKED_EVENTS)
            ->orderBy('event_at')
            ->orderBy('id')
            ->get();
        $latest = $events->last();

        if (! $latest) {
            return;
        }

        $message->forceFill([
            'delivery_status' => $this->deliveryStatus($latest->event_type),
            'delivery_status_at' => $latest->event_at,
            'delivery_error' => $latest->reason,
            'delivered_at' => $events->where('event_type', 'email.delivered')->last()?->event_at,
            'bounced_at' => $events->where('event_type', 'email.bounced')->last()?->event_at,
            'complained_at' => $events->where('event_type', 'email.complained')->last()?->event_at,
        ])->save();
    }

    private function deliveryStatus(string $eventType): string
    {
        return match ($eventType) {
            'email.sent' => 'sent',
            'email.delivered' => 'delivered',
            'email.delivery_delayed' => 'delayed',
            'email.bounced' => 'bounced',
            'email.complained' => 'complained',
            'email.failed', 'email.suppressed' => 'rejected',
        };
    }

    private function reason(string $eventType, array $data): ?string
    {
        return match ($eventType) {
            'email.bounced' => $data['bounce']['message'] ?? null,
            'email.failed' => $data['failed']['reason'] ?? null,
            'email.suppressed' => $data['suppressed']['message'] ?? null,
            default => null,
        };
    }
}
