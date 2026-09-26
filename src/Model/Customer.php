<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\UtcDate;
use DateTimeImmutable;

final readonly class Customer implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $email,
        public ?string $name,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['email'],
            $row['name'],
            UtcDate::fromDb($row['created_at']),
            UtcDate::fromDb($row['updated_at']),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'email'      => $this->email,
            'name'       => $this->name,
            'created_at' => UtcDate::toApi($this->createdAt),
            'updated_at' => UtcDate::toApi($this->updatedAt),
        ];
    }
}
