<?php

declare(strict_types=1);

namespace App\Payment;

use RuntimeException;

final class DisabledGateway implements PaymentGateway
{
    public function request(int $amount, string $callbackUrl, string $description, array $customer = []): array
    {
        throw new RuntimeException('Payment is disabled.');
    }

    public function verify(int $amount, string $authority): array
    {
        throw new RuntimeException('Payment is disabled.');
    }
}
