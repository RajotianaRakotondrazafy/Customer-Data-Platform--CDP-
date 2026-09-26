<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/**
 * "The customer's <aggregate> of <event> <operator> <value>", read from the
 * pre-computed customer_event_stats. e.g. purchase count >= 3, purchase total_amount > 500.
 * A bare {"event": "purchase"} is "purchase count >= 1".
 */
final readonly class AggregateCondition implements Condition
{
    /** @param string $value Numeric string. */
    public function __construct(
        public string $event,
        public Aggregate $aggregate,
        public Operator $operator,
        public string $value,
    ) {
    }

    public function event(): string
    {
        return $this->event;
    }
}
