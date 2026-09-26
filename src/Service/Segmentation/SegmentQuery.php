<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/** A validated POST /api/segments/query body. */
final readonly class SegmentQuery
{
    /** @param list<Condition> $conditions */
    public function __construct(
        public array $conditions,
        public MatchMode $match,
        public int $limit,
        public int $cursor,
    ) {
    }
}
