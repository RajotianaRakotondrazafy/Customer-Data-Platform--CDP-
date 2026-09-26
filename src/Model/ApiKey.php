<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\UtcDate;
use DateTimeImmutable;

/** An API key record. The hash is deliberately not exposed. */
final readonly class ApiKey
{
    public function __construct(
        public int $id,
        public string $name,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            UtcDate::fromDb($row['created_at']),
            UtcDate::fromDbNullable($row['last_used_at']),
            UtcDate::fromDbNullable($row['revoked_at']),
        );
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }
}
