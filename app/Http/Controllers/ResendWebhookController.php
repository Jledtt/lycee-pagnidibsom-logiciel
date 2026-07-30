<?php

namespace App\Http\Controllers;

use App\Services\ResendWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;
use Resend\Exceptions\WebhookSignatureVerificationException;
use Resend\WebhookSignature;

class ResendWebhookController extends Controller
{
    public function __invoke(Request $request, ResendWebhookService $webhooks): JsonResponse
    {
        $secret = (string) config('services.resend.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook non configuré.'], 503);
        }

        $headers = [
            'svix-id' => (string) $request->header('svix-id'),
            'svix-timestamp' => (string) $request->header('svix-timestamp'),
            'svix-signature' => (string) $request->header('svix-signature'),
        ];

        try {
            WebhookSignature::verify($request->getContent(), $headers, $secret);
            $payload = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (WebhookSignatureVerificationException|JsonException $exception) {
            Log::warning('Webhook Resend refusé.', [
                'svix_id' => $headers['svix-id'] ?: null,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Signature ou contenu invalide.'], 400);
        }

        $eventType = $payload['type'] ?? null;

        if (! in_array($eventType, ResendWebhookService::TRACKED_EVENTS, true)) {
            return response()->json(['received' => true, 'tracked' => false]);
        }

        if (
            ! is_string($payload['created_at'] ?? null)
            || ! is_string($payload['data']['email_id'] ?? null)
        ) {
            return response()->json(['message' => 'Événement incomplet.'], 422);
        }

        $created = $webhooks->record($payload, $headers['svix-id']);

        return response()->json([
            'received' => true,
            'duplicate' => ! $created,
        ]);
    }
}
