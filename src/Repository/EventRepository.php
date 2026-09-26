<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\UtcDate;
use App\Model\Event;
use DateTimeImmutable;

final class EventRepository extends AbstractRepository
{
    /** @param string $dedupHash Raw 32-byte SHA-256. */
    public function findIdByHash(string $dedupHash): ?int
    {
        $row = $this->db->fetchOne('SELECT id FROM events WHERE dedup_hash = ?', [$dedupHash]);

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @param array<string, mixed> $properties Raw payload, stored as JSON.
     * @param string               $dedupHash  Raw 32-byte SHA-256.
     * @return int|null New event id, or null if an event with the same hash already exists.
     */
    public function insert(
        int $customerId,
        int $eventTypeId,
        DateTimeImmutable $occurredAt,
        array $properties,
        string $dedupHash,
    ): ?int {
        // Not INSERT IGNORE: it would also silence FK violations and bad values.
        // "id = id" only neutralizes the duplicate-key case (affected rows = 0).
        $affected = $this->db->execute(
            'INSERT INTO events (customer_id, event_type_id, occurred_at, properties, dedup_hash)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = id',
            [
                $customerId,
                $eventTypeId,
                UtcDate::toDb($occurredAt),
                $properties === [] ? null : json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $dedupHash,
            ],
        );

        return $affected === 1 ? $this->db->lastInsertId() : null;
    }

    /**
     * Most recent events of a customer. Served by idx_events_customer_time:
     * reads exactly $limit index entries, no sort.
     *
     * @return list<Event>
     */
    public function latestForCustomer(int $customerId, int $limit = 10): array
    {
        $rows = $this->db->fetchAll(
            'SELECT e.id, e.customer_id, t.name AS event_type, e.occurred_at, e.received_at, e.properties
             FROM events e
             JOIN event_types t ON t.id = e.event_type_id
             WHERE e.customer_id = ?
             ORDER BY e.occurred_at DESC, e.id DESC
             LIMIT ?',
            [$customerId, $limit],
        );

        return array_map(Event::fromRow(...), $rows);
    }
}
