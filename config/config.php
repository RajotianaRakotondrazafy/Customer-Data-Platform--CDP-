<?php

declare(strict_types=1);

return [
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
    'db' => [
        'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name'     => $_ENV['DB_NAME'] ?? 'cdp',
        'user'     => $_ENV['DB_USER'] ?? 'cdp',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
];
