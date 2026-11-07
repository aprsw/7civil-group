<?php

declare(strict_types=1);

use SevenCivilGroup\Core\Http\Request;

/**
 * Single entry point (Front Controller). Every HTTP request is routed here,
 * turned into a Request, handled by the Application kernel, and the resulting
 * Response is emitted as JSON.
 */

/** @var SevenCivilGroup\Core\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->handle(Request::fromGlobals())->send();
