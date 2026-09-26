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
use App\Service\CustomerProfileService;
use App\Service\EventIngestionService;
use DateTimeImmutable;
use Tests\Integration\DatabaseTestCase;

final class CustomerProfileServiceTest extends DatabaseTestCase
{
    private EventIngestionService $ingestion;
    private CustomerProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();
        $customers = new CustomerRepository($this->db);
        $events = new EventRepository($this->db);
        $stats = new CustomerEventStatsRepository($this->db);

        $this->ingestion = new EventIngestionService(
            $this->db,
            $customers,
            $events,
            new EventPropertyRepository($this->db),
            $stats,
            new EventTypeRepository($this->db),
            new PropertyKeyRepository($this->db),
        );
        $this->profiles = new CustomerProfileService($customers, $events, $stats);
    }

    private function ingest(string $event, array $properties, string $at): int
    {
        return $this->ingestion->ingest(
            new IncomingEvent('profile@example.com', 'Profile', $event, $properties, new DateTimeImmutable($at)),
        )->customerId;
    }

    public function testProfileContainsCustomerRecentEventsAndStats(): void
    {
        for ($day = 1; $day <= 9; $day++) {
            $this->ingest('page_view', ['page' => "/p$day"], sprintf('2026-04-%02dT08:00:00Z', $day));
        }
        $this->ingest('purchase', ['amount' => 120, 'product' => 'Shoes'], '2026-04-10T12:00:00Z');
        $this->ingest('purchase', ['amount' => 39.9, 'product' => 'Hat'], '2026-04-11T12:00:00Z');
        $id = $this->ingest('purchase', ['product' => 'Free sample'], '2026-04-12T12:00:00Z');

        $json = json_decode(json_encode($this->profiles->get($id)), true);

        self::assertSame('profile@example.com', $json['customer']['email']);

        self::assertCount(10, $json['recent_events']);
        self::assertSame('2026-04-12T12:00:00.000Z', $json['recent_events'][0]['occurred_at']);
        self::assertSame('purchase', $json['recent_events'][0]['event']);

        self::assertSame(12, $json['stats']['total_events']);
        self::assertSame(3, $json['stats']['total_purchases']);
        self::assertSame('159.900000', $json['stats']['total_spend']);
        self::assertSame('2026-04-01T08:00:00.000Z', $json['stats']['first_seen_at']);
        self::assertSame('2026-04-12T12:00:00.000Z', $json['stats']['last_seen_at']);
        self::assertSame(['page_view', 'purchase'], array_column($json['stats']['by_event'], 'event'));
    }

    public function testCustomerWithoutPurchases(): void
    {
        $id = $this->ingest('signup', [], '2026-04-01T08:00:00Z');

        $stats = json_decode(json_encode($this->profiles->get($id)), true)['stats'];

        self::assertSame(1, $stats['total_events']);
        self::assertSame(0, $stats['total_purchases']);
        self::assertSame('0.000000', $stats['total_spend']);
    }

    public function testUnknownCustomerReturnsNull(): void
    {
        self::assertNull($this->profiles->get(PHP_INT_MAX));
    }
}
