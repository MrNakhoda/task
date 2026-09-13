<?php

declare(strict_types=1);

namespace App\Payment;

use App\Support\Env;
use RuntimeException;

final class PaymentGatewayFactory
{
    public static function make(): PaymentGateway
    {
        return match (strtolower(Env::get('PAYMENT_DRIVER', 'disabled') ?? 'disabled')) {
            'disabled' => new DisabledGateway(),
            'zarinpal' => new ZarinpalGateway(),
            default => throw new RuntimeException('Unsupported payment driver.'),
        };
    }
}
