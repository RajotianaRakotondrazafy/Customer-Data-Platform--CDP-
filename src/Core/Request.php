<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\HttpException;

final class Request
{
    /** @var array<string, string> Route parameters, filled by the router. */
    private array $routeParams = [];

    /** @var array<string, mixed>|null */
    private ?array $decodedJson = null;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers Lower-cased header names.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rtrim($path, '/') ?: '/',
            $_GET,
            $headers,
            (string) file_get_contents('php://input'),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Decoded JSON body. Throws a 400 on malformed JSON.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->decodedJson !== null) {
            return $this->decodedJson;
        }

        if ($this->body === '') {
            return $this->decodedJson = [];
        }

        try {
            $data = json_decode($this->body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new HttpException(400, 'Malformed JSON body: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new HttpException(400, 'JSON body must be an object.');
        }

        return $this->decodedJson = $data;
    }

    /** @param array<string, string> $params */
    public function withRouteParams(array $params): self
    {
        $clone = clone $this;
        $clone->routeParams = $params;

        return $clone;
    }

    public function route(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }
}
