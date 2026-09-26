<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\UtcDate;
use DateTimeImmutable;

/**
 * A stored event. The event type is resolved to its name (join on event_types);
 * properties come from the raw JSON payload.
 */
final readonly class Event implements \JsonSerializable
{
    /** @param array<string, mixed> $properties */
    public function __construct(
        public int $id,
        public int $customerId,
        public string $type,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $receivedAt,
        public array $properties,
    ) {
    }

    /** @param array<string, mixed> $row Expects an `event_type` column (joined name). */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['customer_id'],
            $row['event_type'],
            UtcDate::fromDb($row['occurred_at']),
            UtcDate::fromDb($row['received_at']),
            $row['properties'] === null ? [] : json_decode($row['properties'], true, 64, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'event'       => $this->type,
            'properties'  => (object) $this->properties,
            'occurred_at' => UtcDate::toApi($this->occurredAt),
            'received_at' => UtcDate::toApi($this->receivedAt),
        ];
    }
}
