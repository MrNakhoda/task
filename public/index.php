<?php

declare(strict_types=1);

use App\Http\Request;

/** @var App\Core\Application $application */
$application = require dirname(__DIR__) . '/bootstrap.php';
$application->handle(Request::fromGlobals())->send();
