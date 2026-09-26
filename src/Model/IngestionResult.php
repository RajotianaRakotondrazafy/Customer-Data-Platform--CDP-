<?php

declare(strict_types=1);

namespace App\Model;

final readonly class IngestionResult implements \JsonSerializable
{
    public function __construct(
        public int $eventId,
        public int $customerId,
        public bool $duplicate,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'event_id'    => $this->eventId,
            'customer_id' => $this->customerId,
            'status'      => $this->duplicate ? 'duplicate' : 'created',
        ];
    }
}
