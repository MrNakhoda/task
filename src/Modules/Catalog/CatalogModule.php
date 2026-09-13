<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Core\Module;
use App\Http\Router;

final class CatalogModule implements Module
{
    public function name(): string { return 'catalog'; }
    public function register(Router $router): void { /* Add catalog routes here. */ }
}
