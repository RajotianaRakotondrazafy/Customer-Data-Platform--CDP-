<?php

declare(strict_types=1);

namespace App\Core\Exception;

final class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors Field path => message, e.g. "customer.email" => "Invalid email." */
    public function __construct(public readonly array $errors, string $message = 'Validation failed.')
    {
        parent::__construct($message);
    }
}
