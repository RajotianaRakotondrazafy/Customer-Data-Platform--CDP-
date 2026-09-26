<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $content = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
        );
    }

    /** @param array<string, mixed> $details */
    public static function error(int $status, string $message, array $details = []): self
    {
        $payload = ['error' => ['code' => $status, 'message' => $message]];
        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return self::json($payload, $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }
}
