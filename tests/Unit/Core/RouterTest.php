<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Exception\HttpException;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/api/customers/{id:\d+}', ['CustomerController', 'show']);
        $this->router->post('/api/events', ['EventController', 'store']);
    }

    public function testMatchesRouteAndExtractsParams(): void
    {
        $match = $this->router->match('GET', '/api/customers/42');

        self::assertSame(['CustomerController', 'show'], $match['handler']);
        self::assertSame(['id' => '42'], $match['params']);
    }

    public function testConstraintRejectsNonNumericId(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->router->match('GET', '/api/customers/abc');
    }

    public function testWrongMethodGives405WithAllowHeader(): void
    {
        try {
            $this->router->match('GET', '/api/events');
            self::fail('Expected HttpException');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
            self::assertSame(['Allow' => 'POST'], $e->headers);
        }
    }
}
