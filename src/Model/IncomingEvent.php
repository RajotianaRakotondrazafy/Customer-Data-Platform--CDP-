<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * A validated and normalized POST /api/events payload
 * (email and event name lower-cased, timestamp in UTC).
 */
final readonly class IncomingEvent
{
    /** @param array<string, mixed> $properties */
    public function __construct(
        public string $email,
        public ?string $name,
        public string $event,
        public array $properties,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
