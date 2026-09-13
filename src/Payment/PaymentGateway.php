<?php

declare(strict_types=1);

namespace App\Payment;

interface PaymentGateway
{
    /** @return array{authority: string, redirect_url: string, raw: array} */
    public function request(int $amount, string $callbackUrl, string $description, array $customer = []): array;

    /** @return array{success: bool, reference: ?string, raw: array} */
    public function verify(int $amount, string $authority): array;
}
