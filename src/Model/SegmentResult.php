<?php

declare(strict_types=1);

namespace App\Model;

/** One page of a segment. */
final readonly class SegmentResult implements \JsonSerializable
{
    /** @param list<Customer> $customers */
    public function __construct(
        public array $customers,
        public int $total,
        public int $limit,
        public ?int $nextCursor,
    ) {
    }

    public static function empty(int $limit): self
    {
        return new self([], 0, $limit, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->customers,
            'meta' => [
                'total'       => $this->total,
                'count'       => count($this->customers),
                'limit'       => $this->limit,
                'next_cursor' => $this->nextCursor,
            ],
        ];
    }
}
