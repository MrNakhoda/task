<?php

declare(strict_types=1);

namespace App\Notification;

use App\Support\Env;
use RuntimeException;

final class MailTransport
{
    public function send(array $payload): void
    {
        $driver = strtolower(Env::get('MAIL_DRIVER', 'disabled') ?? 'disabled');
        if ($driver === 'disabled') {
            throw new RuntimeException('Mail driver is disabled.');
        }
        if ($driver !== 'mail') {
            throw new RuntimeException('Unsupported mail driver: ' . $driver);
        }

        $recipient = (string) ($payload['recipient'] ?? '');
        $subject = str_replace(["\r", "\n"], ' ', (string) ($payload['subject'] ?? ''));
        $body = (string) ($payload['body'] ?? '');
        $from = Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.com') ?? 'no-reply@example.com';
        $fromName = Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'App')) ?? 'App';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . sprintf('%s <%s>', str_replace(["\r", "\n"], '', $fromName), $from),
        ];

        if (!mail($recipient, $subject, $body, implode("\r\n", $headers))) {
            throw new RuntimeException('Mail delivery failed.');
        }
    }
}
