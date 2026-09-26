<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\UtcDate;

/** Response of GET /api/customers/{id}: customer, recent events, aggregated statistics. */
final readonly class CustomerProfile implements \JsonSerializable
{
    /**
     * @param list<Event>              $recentEvents Most recent first.
     * @param list<CustomerEventStats> $stats        One entry per event type.
     */
    public function __construct(
        public Customer $customer,
        public array $recentEvents,
        public array $stats,
        public string $purchaseEvent,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $purchases = null;
        foreach ($this->stats as $stat) {
            if ($stat->eventType === $this->purchaseEvent) {
                $purchases = $stat;
            }
        }

        $firstSeen = $this->stats === [] ? null : min(array_map(fn (CustomerEventStats $s) => $s->firstAt, $this->stats));
        $lastSeen = $this->stats === [] ? null : max(array_map(fn (CustomerEventStats $s) => $s->lastAt, $this->stats));

        return [
            'customer'      => $this->customer,
            'recent_events' => $this->recentEvents,
            'stats'         => [
                'total_events'    => array_sum(array_map(fn (CustomerEventStats $s) => $s->eventCount, $this->stats)),
                'total_purchases' => $purchases?->eventCount ?? 0,
                'total_spend'     => $purchases?->totalAmount ?? '0.000000',
                'first_seen_at'   => UtcDate::toApi($firstSeen),
                'last_seen_at'    => UtcDate::toApi($lastSeen),
                'by_event'        => $this->stats,
            ],
        ];
    }
}
