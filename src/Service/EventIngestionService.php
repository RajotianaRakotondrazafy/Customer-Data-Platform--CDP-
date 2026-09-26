<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\UtcDate;
use App\Model\EventProperty;
use App\Model\IncomingEvent;
use App\Model\IngestionResult;
use App\Repository\CustomerEventStatsRepository;
use App\Repository\CustomerRepository;
use App\Repository\EventPropertyRepository;
use App\Repository\EventRepository;
use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;

/**
 * Stores one event: customer upsert, event, typed properties, aggregates.
 *
 * Properties are indexed (event_properties) only when they are scalars that fit
 * the typed columns; everything else (nested objects, long strings, huge numbers)
 * is kept in events.properties JSON only and is not segmentable.
 */
final class EventIngestionService
{
    /** Numeric property summed into customer_event_stats.total_amount. */
    public const AMOUNT_PROPERTY = 'amount';

    public function __construct(
        private readonly Database $db,
        private readonly CustomerRepository $customers,
        private readonly EventRepository $events,
        private readonly EventPropertyRepository $eventProperties,
        private readonly CustomerEventStatsRepository $stats,
        private readonly EventTypeRepository $eventTypes,
        private readonly PropertyKeyRepository $propertyKeys,
    ) {
    }

    public function ingest(IncomingEvent $event): IngestionResult
    {
        // Dictionary ids are resolved (and created) BEFORE the transaction: they are
        // committed immediately, so a rollback can't leave ids in the cache that no
        // longer exist, and the transaction doesn't hold locks on the dictionaries.
        $eventTypeId = $this->eventTypes->idFor($event->event);
        $indexed = $this->indexableProperties($event->properties);
        $keyIds = $indexed === [] ? [] : $this->propertyKeys->idsFor(array_keys($indexed));

        $hash = self::dedupHash($event);

        return $this->db->transaction(function () use ($event, $eventTypeId, $indexed, $keyIds, $hash): IngestionResult {
            $customerId = $this->customers->upsert($event->email, $event->name);

            $eventId = $this->events->insert($customerId, $eventTypeId, $event->occurredAt, $event->properties, $hash);
            if ($eventId === null) {
                // Replay of an already stored event: idempotent, nothing else is counted.
                return new IngestionResult((int) $this->events->findIdByHash($hash), $customerId, true);
            }

            $rows = [];
            foreach ($indexed as $key => [$isNumeric, $value]) {
                $rows[] = $isNumeric
                    ? EventProperty::numeric($keyIds[$key], $value)
                    : EventProperty::string($keyIds[$key], $value);
            }
            $this->eventProperties->insertMany($eventId, $customerId, $eventTypeId, $rows);

            [$amountIsNumeric, $amount] = $indexed[self::AMOUNT_PROPERTY] ?? [false, null];
            $this->stats->record($customerId, $eventTypeId, $amountIsNumeric ? $amount : '0', $event->occurredAt);

            return new IngestionResult($eventId, $customerId, false);
        });
    }

    /**
     * Properties that fit the typed columns (see PropertyValue); the others stay JSON only.
     *
     * @param array<string, mixed> $properties
     * @return array<string, array{bool, string}> key => [is numeric, value]
     */
    private function indexableProperties(array $properties): array
    {
        $indexed = [];
        foreach ($properties as $key => $value) {
            $normalized = PropertyValue::normalize($value);
            if ($normalized !== null) {
                $indexed[(string) $key] = $normalized;
            }
        }

        return $indexed;
    }

    /**
     * SHA-256 of the event identity: same customer + event + timestamp + properties
     * = same event, whatever the key order in the payload.
     */
    public static function dedupHash(IncomingEvent $event): string
    {
        return hash('sha256', json_encode([
            $event->email,
            $event->event,
            UtcDate::toDb($event->occurredAt),
            self::sortKeysRecursive($event->properties),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), true);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function sortKeysRecursive(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn ($v) => is_array($v) ? self::sortKeysRecursive($v) : $v, $data);
    }
}
