<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\HttpException;

/**
 * Minimal regex router.
 *
 * Paths support placeholders: "/api/customers/{id}" or with a constraint "/api/customers/{id:\d+}".
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: array{class-string, string}}> */
    private array $routes = [];

    /** @param array{class-string, string} $handler */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** @param array{class-string, string} $handler */
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** @param array{class-string, string} $handler */
    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => $this->compile($path),
            'handler' => $handler,
        ];
    }

    /**
     * @return array{handler: array{class-string, string}, params: array<string, string>}
     *
     * @throws HttpException 404 if no path matches, 405 if the path matches with another method.
     */
    public function match(string $method, string $path): array
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            return [
                'handler' => $route['handler'],
                'params'  => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
            ];
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404, 'Route not found.');
    }

    private function compile(string $path): string
    {
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            static fn (array $m): string => sprintf('(?P<%s>%s)', $m[1], $m[2] ?? '[^/]+'),
            rtrim($path, '/') ?: '/',
        );

        return '#^' . $regex . '$#';
    }
}
