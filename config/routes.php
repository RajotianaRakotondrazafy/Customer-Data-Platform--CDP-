<?php

declare(strict_types=1);

use App\Controller\CustomerController;
use App\Controller\EventController;
use App\Controller\HealthController;
use App\Controller\SegmentController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/api/health', [HealthController::class, 'index']);

    $router->post('/api/events', [EventController::class, 'store']);
    $router->get('/api/customers/{id:\d+}', [CustomerController::class, 'show']);
    $router->post('/api/segments/query', [SegmentController::class, 'query']);
};
