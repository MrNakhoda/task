<?php

declare(strict_types=1);

use App\Modules\Catalog\CatalogModule;
use App\Modules\Commerce\CommerceModule;
use App\Modules\Crm\CrmModule;
use App\Modules\Hr\HrModule;
use App\Modules\Workflow\WorkflowModule;

return [
    'workflow' => [
        'class' => WorkflowModule::class,
        'migrations' => APP_ROOT . '/database/modules/workflow',
        'depends' => [],
    ],
    'catalog' => [
        'class' => CatalogModule::class,
        'migrations' => APP_ROOT . '/database/modules/catalog',
        'depends' => [],
    ],
    'commerce' => [
        'class' => CommerceModule::class,
        'migrations' => APP_ROOT . '/database/modules/commerce',
        'depends' => ['catalog'],
    ],
    'crm' => [
        'class' => CrmModule::class,
        'migrations' => APP_ROOT . '/database/modules/crm',
        'depends' => [],
    ],
    'hr' => [
        'class' => HrModule::class,
        'migrations' => APP_ROOT . '/database/modules/hr',
        'depends' => [],
    ],
];
