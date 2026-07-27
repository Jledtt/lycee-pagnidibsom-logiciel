<?php

namespace App\Jobs;

use App\Mail\BusinessNotificationMail;
use App\Models\CommunicationMessage;
use App\Services\CommunicationQuotaService;
use App\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCommunicationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $timeout = 60;

    public function __construct(public readonly int $messageId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(
        CommunicationQuotaService $quota,
        CommunicationService $communications,
    ): void {
        $message = CommunicationMessage::query()->find($this->messageId);

        if (! $message || in_array($message->status, ['sent', 'skipped'], true)) {
            return;
        }

        $message->increment('attempts');

        if (! config('communication.mail_enabled', true)) {
            $message->forceFill([
                'status' => 'skipped',
                'error_message' => 'Envoi désactivé par la configuration.',
            ])->save();
            $communications->refreshCampaign($message->campaign);

            return;
        }

        $availability = $quota->availability();

        if (! $availability['available']) {
            $message->forceFill([
                'status' => 'deferred',
                'error_message' => $availability['reason'],
            ])->save();
            $communications->refreshCampaign($message->campaign);
            $this->release($quota->retryDelay($availability['retry_at']));

            return;
        }

        $message->forceFill([
            'status' => 'queued',
            'error_message' => null,
        ])->save();

        try {
            $sent = Mail::to($message->recipient_email, $message->recipient_name)
                ->send(new BusinessNotificationMail(
                    $message->subject,
                    $message->recipient_name,
                    $message->body,
                ));
            $originalMessage = $sent?->getOriginalMessage();
            $providerMessageId = $originalMessage && method_exists($originalMessage, 'getHeaders')
                ? $originalMessage->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString()
                : null;

            $message->forceFill([
                'status' => 'sent',
                'provider_message_id' => $providerMessageId,
                'error_message' => null,
                'sent_at' => now(),
                'failed_at' => null,
            ])->save();

            $communications->refreshCampaign($message->campaign);
        } catch (Throwable $exception) {
            $message->forceFill([
                'status' => 'queued',
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $message = CommunicationMessage::query()->find($this->messageId);

        if (! $message || $message->status === 'sent') {
            return;
        }

        $message->forceFill([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ])->save();

        app(CommunicationService::class)->refreshCampaign($message->campaign);
    }
}
