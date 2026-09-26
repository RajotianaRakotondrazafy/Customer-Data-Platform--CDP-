<?php

declare(strict_types=1);

// Built-in PHP server: let it serve static files directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Kernel;
use App\Core\Request;

$kernel = Kernel::boot(dirname(__DIR__));
$kernel->handle(Request::fromGlobals())->send();
