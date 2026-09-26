<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\HttpException;
use App\Core\Exception\ValidationException;

/**
 * Wires config, container and router together, and turns exceptions into JSON errors.
 */
final class Kernel
{
    private function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly bool $debug,
    ) {
    }

    public static function boot(string $rootDir): self
    {
        self::loadEnv($rootDir . '/.env');
        $config = require $rootDir . '/config/config.php';

        $container = new Container();
        $container->set(Database::class, static fn (): Database => new Database($config['db']));
        $container->set(View::class, static fn (): View => new View($rootDir . '/views'));

        $router = new Router();
        (require $rootDir . '/config/routes.php')($router);

        return new self($container, $router, $config['debug']);
    }

    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request->method, $request->path);
            [$class, $method] = $match['handler'];

            return $this->container->get($class)->$method($request->withRouteParams($match['params']));
        } catch (ValidationException $e) {
            return Response::error(422, $e->getMessage(), $e->errors);
        } catch (HttpException $e) {
            $response = Response::error($e->status, $e->getMessage());

            return new Response($response->content, $response->status, $response->headers + $e->headers);
        } catch (\Throwable $e) {
            error_log((string) $e);

            return Response::error(
                500,
                'Internal server error.',
                $this->debug ? ['exception' => $e::class, 'message' => $e->getMessage()] : [],
            );
        }
    }

    public function container(): Container
    {
        return $this->container;
    }

    /** Minimal .env loader (KEY=VALUE lines, # comments). Real env vars take precedence. */
    private static function loadEnv(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $_ENV[$key] = getenv($key) === false ? trim($value, "\"'") : getenv($key);
        }
    }
}
