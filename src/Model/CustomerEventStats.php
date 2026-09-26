<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\UtcDate;
use DateTimeImmutable;

/** Pre-computed aggregates of one customer for one event type. */
final readonly class CustomerEventStats implements \JsonSerializable
{
    public function __construct(
        public string $eventType,
        public int $eventCount,
        public string $totalAmount,
        public DateTimeImmutable $firstAt,
        public DateTimeImmutable $lastAt,
    ) {
    }

    /** @param array<string, mixed> $row Expects an `event_type` column (joined name). */
    public static function fromRow(array $row): self
    {
        return new self(
            $row['event_type'],
            (int) $row['event_count'],
            (string) $row['total_amount'],
            UtcDate::fromDb($row['first_at']),
            UtcDate::fromDb($row['last_at']),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'event'        => $this->eventType,
            'count'        => $this->eventCount,
            'total_amount' => $this->totalAmount,
            'first_at'     => UtcDate::toApi($this->firstAt),
            'last_at'      => UtcDate::toApi($this->lastAt),
        ];
    }
}
