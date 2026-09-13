<?php

declare(strict_types=1);

namespace App\Modules\Crm;

use App\Core\Module;
use App\Http\Router;

final class CrmModule implements Module
{
    public function name(): string { return 'crm'; }
    public function register(Router $router): void { /* Add CRM routes here. */ }
}
