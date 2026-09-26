<?php

declare(strict_types=1);

use App\Controller\CustomerController;
use App\Controller\EventController;
use App\Controller\HealthController;
use App\Controller\SegmentController;
use App\Controller\Web\CustomerPageController;
use App\Controller\Web\EventPageController;
use App\Controller\Web\SegmentPageController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/api/health', [HealthController::class, 'index']);

    $router->post('/api/events', [EventController::class, 'store']);
    $router->get('/api/customers/{id:\d+}', [CustomerController::class, 'show']);
    $router->post('/api/segments/query', [SegmentController::class, 'query']);

    $router->get('/', [CustomerPageController::class, 'home']);
    $router->get('/customers', [CustomerPageController::class, 'index']);
    $router->get('/customers/{id:\d+}', [CustomerPageController::class, 'show']);
    $router->get('/events', [EventPageController::class, 'index']);
    $router->get('/segments', [SegmentPageController::class, 'index']);
};
