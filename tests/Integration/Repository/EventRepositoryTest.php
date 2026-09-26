<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Model\EventProperty;
use App\Repository\CustomerEventStatsRepository;
use App\Repository\CustomerRepository;
use App\Repository\EventPropertyRepository;
use App\Repository\EventRepository;
use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;
use DateTimeImmutable;
use Tests\Integration\DatabaseTestCase;

final class EventRepositoryTest extends DatabaseTestCase
{
    private EventRepository $events;
    private int $customerId;
    private int $purchaseTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = new EventRepository($this->db);
        $this->customerId = (new CustomerRepository($this->db))->upsert('events@example.com', 'Events');
        $this->purchaseTypeId = (new EventTypeRepository($this->db))->idFor('purchase');
    }

    public function testInsertIsDeduplicatedByHash(): void
    {
        $hash = hash('sha256', 'same-event', true);
        $at = new DateTimeImmutable('2026-04-10T12:00:00Z');

        $id = $this->events->insert($this->customerId, $this->purchaseTypeId, $at, ['amount' => 120], $hash);
        $dup = $this->events->insert($this->customerId, $this->purchaseTypeId, $at, ['amount' => 120], $hash);

        self::assertIsInt($id);
        self::assertNull($dup);
    }

    public function testLatestForCustomerIsOrderedAndLimited(): void
    {
        for ($day = 1; $day <= 12; $day++) {
            $this->events->insert(
                $this->customerId,
                $this->purchaseTypeId,
                new DateTimeImmutable(sprintf('2026-04-%02dT10:00:00Z', $day)),
                ['amount' => $day, 'product' => 'Shoes'],
                hash('sha256', "event-$day", true),
            );
        }

        $latest = $this->events->latestForCustomer($this->customerId);

        self::assertCount(10, $latest);
        self::assertSame('2026-04-12T10:00:00.000Z', $latest[0]->jsonSerialize()['occurred_at']);
        self::assertSame('2026-04-03T10:00:00.000Z', $latest[9]->jsonSerialize()['occurred_at']);
        self::assertSame('purchase', $latest[0]->type);
        self::assertSame(['amount' => 12, 'product' => 'Shoes'], $latest[0]->properties);
    }

    public function testPropertiesAndStats(): void
    {
        $keys = (new PropertyKeyRepository($this->db))->idsFor(['amount', 'product']);
        $stats = new CustomerEventStatsRepository($this->db);

        foreach ([['120.50', '2026-04-10T12:00:00Z'], ['30', '2026-04-05T08:00:00Z']] as $i => [$amount, $at]) {
            $date = new DateTimeImmutable($at);
            $eventId = $this->events->insert(
                $this->customerId,
                $this->purchaseTypeId,
                $date,
                ['amount' => (float) $amount],
                hash('sha256', "stats-$i", true),
            );
            (new EventPropertyRepository($this->db))->insertMany($eventId, $this->customerId, $this->purchaseTypeId, [
                EventProperty::numeric($keys['amount'], $amount),
                EventProperty::string($keys['product'], 'Shoes'),
            ]);
            $stats->record($this->customerId, $this->purchaseTypeId, $amount, $date);
        }

        $count = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM event_properties WHERE customer_id = ? AND property_key_id = ? AND value_num > 100',
            [$this->customerId, $keys['amount']],
        );
        self::assertSame(1, (int) $count['n']);

        [$purchase] = $stats->forCustomer($this->customerId);
        self::assertSame('purchase', $purchase->eventType);
        self::assertSame(2, $purchase->eventCount);
        self::assertSame('150.500000', $purchase->totalAmount);
        self::assertSame('2026-04-05T08:00:00.000Z', $purchase->jsonSerialize()['first_at']);
        self::assertSame('2026-04-10T12:00:00.000Z', $purchase->jsonSerialize()['last_at']);
    }
}
