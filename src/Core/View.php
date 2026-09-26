<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $params */
    public function render(string $template, array $params = [], int $status = 200): Response
    {
        $content = $this->capture($template, $params);
        $html = $this->capture('layout', [
            'content' => $content,
            'title'   => $params['title'] ?? 'CDP',
            'active'  => $params['active'] ?? '',
        ]);

        return new Response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location): Response
    {
        return new Response('', 302, ['Location' => $location]);
    }

    /** @param array<string, mixed> $params */
    private function capture(string $template, array $params): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = static fn (string $path, array $query = []): string => $path
            . (($query = array_filter($query, fn ($v) => $v !== null && $v !== '' && $v !== [])) ? '?' . http_build_query($query) : '');

        extract($params, EXTR_SKIP);
        ob_start();
        try {
            require $file;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
