<?php

declare(strict_types=1);

namespace App\Modules\Commerce;

use App\Core\Module;
use App\Http\Router;

final class CommerceModule implements Module
{
    public function name(): string { return 'commerce'; }
    public function register(Router $router): void { /* Add commerce routes here. */ }
}
