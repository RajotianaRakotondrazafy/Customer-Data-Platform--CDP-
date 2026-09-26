<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\UtcDate;
use App\Model\CustomerEventStats;
use DateTimeImmutable;

final class CustomerEventStatsRepository extends AbstractRepository
{
    /**
     * Adds one event to the customer's aggregates for this event type (atomic upsert,
     * safe under concurrent ingestion: the increment happens inside MySQL).
     *
     * @param string $amount Numeric string added to total_amount ("0" when not applicable).
     */
    public function record(int $customerId, int $eventTypeId, string $amount, DateTimeImmutable $occurredAt): void
    {
        $at = UtcDate::toDb($occurredAt);

        $this->db->execute(
            'INSERT INTO customer_event_stats
                 (customer_id, event_type_id, event_count, total_amount, first_at, last_at)
             VALUES (?, ?, 1, ?, ?, ?) AS incoming
             ON DUPLICATE KEY UPDATE
                 event_count  = customer_event_stats.event_count + 1,
                 total_amount = customer_event_stats.total_amount + incoming.total_amount,
                 first_at     = LEAST(customer_event_stats.first_at, incoming.first_at),
                 last_at      = GREATEST(customer_event_stats.last_at, incoming.last_at)',
            [$customerId, $eventTypeId, $amount, $at, $at],
        );
    }

    /**
     * Totals for a page of customers (PK reads on customer_event_stats, no event scan).
     *
     * @param list<int> $customerIds
     * @return array<int, array{events: int, spend: string}> customer id => totals
     */
    public function totalsFor(array $customerIds, string $purchaseEvent): array
    {
        if ($customerIds === []) {
            return [];
        }

        $rows = $this->db->fetchAll(
            sprintf(
                'SELECT s.customer_id,
                        SUM(s.event_count) AS events,
                        SUM(IF(t.name = ?, s.total_amount, 0)) AS spend
                 FROM customer_event_stats s
                 JOIN event_types t ON t.id = s.event_type_id
                 WHERE s.customer_id IN (%s)
                 GROUP BY s.customer_id',
                self::placeholders(count($customerIds)),
            ),
            [$purchaseEvent, ...$customerIds],
        );

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['customer_id']] = ['events' => (int) $row['events'], 'spend' => (string) $row['spend']];
        }

        return $totals;
    }

    /**
     * All aggregates of a customer (one PK range read).
     *
     * @return list<CustomerEventStats> Ordered by event type name.
     */
    public function forCustomer(int $customerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.name AS event_type, s.event_count, s.total_amount, s.first_at, s.last_at
             FROM customer_event_stats s
             JOIN event_types t ON t.id = s.event_type_id
             WHERE s.customer_id = ?
             ORDER BY t.name',
            [$customerId],
        );

        return array_map(CustomerEventStats::fromRow(...), $rows);
    }
}
