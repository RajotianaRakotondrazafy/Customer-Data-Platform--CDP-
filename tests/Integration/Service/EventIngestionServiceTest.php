<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Model\IncomingEvent;
use App\Repository\CustomerEventStatsRepository;
use App\Repository\CustomerRepository;
use App\Repository\EventPropertyRepository;
use App\Repository\EventRepository;
use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;
use App\Service\EventIngestionService;
use DateTimeImmutable;
use Tests\Integration\DatabaseTestCase;

final class EventIngestionServiceTest extends DatabaseTestCase
{
    private EventIngestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventIngestionService(
            $this->db,
            new CustomerRepository($this->db),
            new EventRepository($this->db),
            new EventPropertyRepository($this->db),
            new CustomerEventStatsRepository($this->db),
            new EventTypeRepository($this->db),
            new PropertyKeyRepository($this->db),
        );
    }

    private static function purchase(string $email, array $properties, string $at = '2026-04-10T12:00:00Z'): IncomingEvent
    {
        return new IncomingEvent($email, 'John Doe', 'purchase', $properties, new DateTimeImmutable($at));
    }

    public function testIngestStoresEventTypedPropertiesAndStats(): void
    {
        $result = $this->service->ingest(self::purchase('ingest@example.com', [
            'amount'   => 120.5,
            'product'  => 'Shoes',
            'gift'     => true,
            'tags'     => ['summer', 'sale'],      // nested: JSON only
            'note'     => str_repeat('x', 300),    // too long: JSON only
            'coupon'   => null,                    // null: JSON only
        ]));

        self::assertFalse($result->duplicate);

        $rows = $this->db->fetchAll(
            'SELECT k.name, p.value_num, p.value_str, p.customer_id, p.event_type_id
             FROM event_properties p JOIN property_keys k ON k.id = p.property_key_id
             WHERE p.event_id = ? ORDER BY k.name',
            [$result->eventId],
        );
        self::assertSame(['amount', 'gift', 'product'], array_column($rows, 'name'));
        self::assertSame('120.500000', $rows[0]['value_num']);
        self::assertSame('1.000000', $rows[1]['value_num']);
        self::assertSame('Shoes', $rows[2]['value_str']);
        self::assertSame($result->customerId, (int) $rows[0]['customer_id']);

        $event = (new EventRepository($this->db))->latestForCustomer($result->customerId, 1)[0];
        self::assertSame(['summer', 'sale'], $event->properties['tags'], 'raw JSON keeps everything');

        [$stats] = (new CustomerEventStatsRepository($this->db))->forCustomer($result->customerId);
        self::assertSame(1, $stats->eventCount);
        self::assertSame('120.500000', $stats->totalAmount);
    }

    public function testReplayedEventIsDeduplicatedAndNotCountedTwice(): void
    {
        $first = $this->service->ingest(self::purchase('dedup@example.com', ['amount' => 50, 'product' => 'Hat']));
        // Same event, different key order.
        $replay = $this->service->ingest(self::purchase('dedup@example.com', ['product' => 'Hat', 'amount' => 50]));

        self::assertTrue($replay->duplicate);
        self::assertSame($first->eventId, $replay->eventId);

        [$stats] = (new CustomerEventStatsRepository($this->db))->forCustomer($first->customerId);
        self::assertSame(1, $stats->eventCount);
        self::assertSame('50.000000', $stats->totalAmount);
    }

    public function testStatsAccumulateAcrossEvents(): void
    {
        $a = $this->service->ingest(self::purchase('sum@example.com', ['amount' => 100], '2026-04-10T12:00:00Z'));
        $this->service->ingest(self::purchase('sum@example.com', ['amount' => 20.25], '2026-04-01T09:00:00Z'));
        $this->service->ingest(self::purchase('sum@example.com', ['product' => 'no amount'], '2026-04-12T09:00:00Z'));

        [$stats] = (new CustomerEventStatsRepository($this->db))->forCustomer($a->customerId);
        self::assertSame(3, $stats->eventCount);
        self::assertSame('120.250000', $stats->totalAmount);
        self::assertSame('2026-04-01T09:00:00.000Z', $stats->jsonSerialize()['first_at']);
        self::assertSame('2026-04-12T09:00:00.000Z', $stats->jsonSerialize()['last_at']);
    }
}
