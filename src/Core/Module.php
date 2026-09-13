<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Router;

interface Module
{
    public function name(): string;

    public function register(Router $router): void;
}
