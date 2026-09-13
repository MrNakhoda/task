# Adding a module

1. Create `src/Modules/Example/ExampleModule.php` implementing `App\\Core\\Module`.
2. Put schema changes in `database/modules/example/NNN_description.sql`.
3. Add the module to `config/modules.php`, including dependencies if any.
4. Add `example` to `APP_MODULES`.
5. Run `php bin/console migrate`.

Example:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Example;

use App\Core\Module;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

final class ExampleModule implements Module
{
    public function name(): string
    {
        return 'example';
    }

    public function register(Router $router): void
    {
        $router->get('/api/v1/example', static fn (Request $request): Response =>
            Response::json(['ok' => true])
        );
    }
}
```

Use `{{prefix}}` in SQL table names. The migration runner replaces it with the validated `DB_TABLE_PREFIX` value.

Do not expose generic CRUD just because a table exists. Define who can list, view, create, update and delete each resource; validate ownership in the repository query as well as in the controller.
