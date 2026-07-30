<?php

namespace Tests\Feature;

use App\Models\CommunicationEmailEvent;
use App\Models\CommunicationMessage;
use App\Services\ResendWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_BYTES = 'lpp-resend-webhook-test-secret-32';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.resend.webhook_secret', 'whsec_'.base64_encode(self::SECRET_BYTES));
    }

    public function test_signed_delivery_is_recorded_and_duplicate_is_ignored(): void
    {
        $message = $this->message('resend-delivered-1');
        $payload = $this->payload('email.delivered', $message->provider_message_id);

        $this->sendWebhook($payload, 'evt-delivered-1')
            ->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);
        $this->sendWebhook($payload, 'evt-delivered-1')
            ->assertOk()
            ->assertJson(['received' => true, 'duplicate' => true]);

        $message->refresh();
        $this->assertSame('delivered', $message->delivery_status);
        $this->assertNotNull($message->delivered_at);
        $this->assertDatabaseCount('communication_email_events', 1);
        $this->assertDatabaseHas('communication_email_events', [
            'communication_message_id' => $message->id,
            'svix_id' => 'evt-delivered-1',
            'event_type' => 'email.delivered',
        ]);
    }

    public function test_invalid_signature_is_rejected_without_storing_event(): void
    {
        $payload = json_encode(
            $this->payload('email.delivered', 'resend-invalid-signature'),
            JSON_THROW_ON_ERROR,
        );

        $this->call('POST', '/api/webhooks/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => 'evt-invalid',
            'HTTP_SVIX_TIMESTAMP' => (string) now()->timestamp,
            'HTTP_SVIX_SIGNATURE' => 'v1,signature-invalide',
        ], $payload)->assertStatus(400);

        $this->assertDatabaseCount('communication_email_events', 0);
    }

    public function test_older_event_does_not_replace_newer_delivery_status(): void
    {
        $message = $this->message('resend-out-of-order');
        $complained = $this->payload(
            'email.complained',
            $message->provider_message_id,
            now()->toIso8601String(),
        );
        $delivered = $this->payload(
            'email.delivered',
            $message->provider_message_id,
            now()->subMinute()->toIso8601String(),
        );

        $this->sendWebhook($complained, 'evt-complained')->assertOk();
        $this->sendWebhook($delivered, 'evt-delivered-late')->assertOk();

        $message->refresh();
        $this->assertSame('complained', $message->delivery_status);
        $this->assertNotNull($message->delivered_at);
        $this->assertNotNull($message->complained_at);
        $this->assertDatabaseCount('communication_email_events', 2);
    }

    public function test_bounce_reason_is_visible_and_unmatched_event_can_be_reconciled(): void
    {
        $providerMessageId = 'resend-before-local-link';
        $payload = $this->payload('email.bounced', $providerMessageId);
        $payload['data']['bounce'] = [
            'message' => 'Mailbox unavailable',
            'type' => 'Permanent',
            'subType' => 'MessageRejected',
        ];

        $this->sendWebhook($payload, 'evt-bounced')->assertOk();
        $event = CommunicationEmailEvent::query()->firstOrFail();
        $this->assertNull($event->communication_message_id);

        $message = $this->message($providerMessageId);
        app(ResendWebhookService::class)->reconcileMessage($message);

        $message->refresh();
        $this->assertSame('bounced', $message->delivery_status);
        $this->assertSame('Mailbox unavailable', $message->delivery_error);
        $this->assertNotNull($message->bounced_at);
        $this->assertSame($message->id, $event->refresh()->communication_message_id);
    }

    public function test_failed_event_is_recorded_as_rejected_with_its_reason(): void
    {
        $message = $this->message('resend-failed');
        $payload = $this->payload('email.failed', $message->provider_message_id);
        $payload['data']['failed'] = ['reason' => 'provider_error'];

        $this->sendWebhook($payload, 'evt-failed')->assertOk();

        $message->refresh();
        $this->assertSame('rejected', $message->delivery_status);
        $this->assertSame('provider_error', $message->delivery_error);
    }

    private function message(string $providerMessageId): CommunicationMessage
    {
        return CommunicationMessage::query()->create([
            'event_type' => 'announcement',
            'recipient_name' => 'Parent Test',
            'recipient_email' => 'parent.test@gmail.com',
            'subject' => 'Information',
            'body' => 'Message',
            'status' => 'sent',
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
        ]);
    }

    private function payload(string $type, string $providerMessageId, ?string $createdAt = null): array
    {
        return [
            'type' => $type,
            'created_at' => $createdAt ?? now()->toIso8601String(),
            'data' => [
                'email_id' => $providerMessageId,
                'created_at' => now()->subSecond()->toIso8601String(),
                'from' => 'LPP <notifications@gestion.lyceepagnidibsom.com>',
                'to' => ['parent.test@gmail.com'],
                'subject' => 'Information',
            ],
        ];
    }

    private function sendWebhook(array $event, string $svixId): TestResponse
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = base64_encode(hash_hmac(
            'sha256',
            "{$svixId}.{$timestamp}.{$payload}",
            self::SECRET_BYTES,
            true,
        ));

        return $this->call('POST', '/api/webhooks/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $svixId,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
        ], $payload);
    }
}
