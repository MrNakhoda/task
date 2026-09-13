<?php

declare(strict_types=1);

namespace App\Modules\Hr;

use App\Core\Module;
use App\Http\Router;

final class HrModule implements Module
{
    public function name(): string { return 'hr'; }
    public function register(Router $router): void { /* Add HR routes here. */ }
}
