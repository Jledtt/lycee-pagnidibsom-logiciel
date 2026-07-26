<?php

namespace Tests\Feature;

use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ResendTransport;
use Tests\TestCase;

class ResendMailConfigurationTest extends TestCase
{
    public function test_resend_transport_can_be_created_without_sending_email(): void
    {
        config()->set('services.resend.key', 're_test_key');

        $transport = app(MailManager::class)
            ->mailer('resend')
            ->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
        $this->assertSame('resend', (string) $transport);
    }
}
