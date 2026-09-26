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
use App\Repository\SegmentRepository;
use App\Service\EventIngestionService;
use App\Service\Segmentation\SegmentationService;
use App\Service\Segmentation\SegmentSqlBuilder;
use App\Validation\SegmentQueryValidator;
use DateTimeImmutable;
use Tests\Integration\DatabaseTestCase;

/**
 * Dataset (prefixed emails so tests don't depend on other rows):
 *   alice : purchase 120 Shoes (gift), purchase 30 Hat, purchase 400 "50% off coat"   -> 3 purchases, 550 total
 *   bob   : purchase 80 Shoes, signup
 *   carol : signup, page_view
 *   dave  : purchase 150 Hat
 */
final class SegmentationServiceTest extends DatabaseTestCase
{
    private SegmentationService $segmentation;
    private SegmentQueryValidator $validator;
    private SegmentSqlBuilder $builder;
    /** @var array<string, int> name => customer id */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $customers = new CustomerRepository($this->db);
        $eventTypes = new EventTypeRepository($this->db);
        $propertyKeys = new PropertyKeyRepository($this->db);
        $this->builder = new SegmentSqlBuilder();

        $ingestion = new EventIngestionService(
            $this->db,
            $customers,
            new EventRepository($this->db),
            new EventPropertyRepository($this->db),
            new CustomerEventStatsRepository($this->db),
            $eventTypes,
            $propertyKeys,
        );
        $this->segmentation = new SegmentationService(
            new SegmentRepository($this->db, $this->builder),
            $customers,
            $eventTypes,
            $propertyKeys,
        );
        $this->validator = new SegmentQueryValidator();

        // Isolate from rows left by other runs: segment results are filtered to this dataset.
        $this->db->execute("DELETE FROM customers WHERE email LIKE 'seg-%'");

        $day = 0;
        foreach ([
            ['alice', 'purchase', ['amount' => 120, 'product' => 'Shoes', 'gift' => true]],
            ['alice', 'purchase', ['amount' => 30, 'product' => 'Hat']],
            ['alice', 'purchase', ['amount' => 400, 'product' => '50% off coat']],
            ['bob', 'purchase', ['amount' => 80, 'product' => 'shoes']],
            ['bob', 'signup', []],
            ['carol', 'signup', []],
            ['carol', 'page_view', ['page' => '/home']],
            ['dave', 'purchase', ['amount' => 150, 'product' => 'Hat']],
        ] as [$who, $event, $properties]) {
            $result = $ingestion->ingest(new IncomingEvent(
                "seg-$who@example.com",
                ucfirst($who),
                $event,
                $properties,
                new DateTimeImmutable(sprintf('2026-04-%02dT10:00:00Z', ++$day)),
            ));
            $this->ids[$who] = $result->customerId;
        }
    }

    /**
     * @param list<array<string, mixed>> $conditions
     * @return list<string> Matching customer names of this dataset, in id order.
     */
    private function names(array $conditions, string $match = 'all'): array
    {
        $result = $this->segmentation->query(
            $this->validator->validate(['conditions' => $conditions, 'match' => $match, 'limit' => 1000]),
        );
        $byId = array_flip($this->ids);

        return array_values(array_filter(array_map(fn ($c) => $byId[$c->id] ?? null, $result->customers)));
    }

    public function testSpecificationExample(): void
    {
        self::assertSame(['alice', 'dave'], $this->names([
            ['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100],
        ]));
    }

    public function testNumericOperators(): void
    {
        $amount = fn (string $op, mixed $v) => $this->names([
            ['event' => 'purchase', 'property' => 'amount', 'operator' => $op, 'value' => $v],
        ]);

        self::assertSame(['alice', 'bob', 'dave'], $amount('>=', 80));
        self::assertSame(['alice', 'bob'], $amount('<', 100));
        self::assertSame(['alice'], $amount('=', 30));
        self::assertSame(['alice', 'bob'], $amount('<=', 80.0));
        self::assertSame(['alice', 'bob', 'dave'], $amount('!=', 30), 'alice has other purchases != 30');
        self::assertSame(['bob', 'dave'], $amount('in', [80, 150]));
    }

    public function testStringOperatorsAreCaseInsensitive(): void
    {
        $product = fn (string $op, mixed $v) => $this->names([
            ['event' => 'purchase', 'property' => 'product', 'operator' => $op, 'value' => $v],
        ]);

        self::assertSame(['alice', 'bob'], $product('=', 'SHOES'));
        self::assertSame(['alice', 'dave'], $product('in', ['hat']));
        self::assertSame(['alice'], $product('contains', '50%'), '% is literal, not a wildcard');
        self::assertSame(['alice', 'bob'], $product('starts_with', 'sho'));
    }

    public function testBooleanProperty(): void
    {
        self::assertSame(['alice'], $this->names([
            ['event' => 'purchase', 'property' => 'gift', 'operator' => '=', 'value' => true],
        ]));
    }

    public function testAllIntersectsAndAnyUnites(): void
    {
        $conditions = [
            ['event' => 'purchase', 'property' => 'product', 'operator' => '=', 'value' => 'shoes'],
            ['event' => 'signup'],
        ];

        self::assertSame(['bob'], $this->names($conditions, 'all'));
        self::assertSame(['alice', 'bob', 'carol'], $this->names($conditions, 'any'));
    }

    public function testAggregateConditions(): void
    {
        self::assertSame(['alice'], $this->names([
            ['event' => 'purchase', 'aggregate' => 'count', 'operator' => '>=', 'value' => 3],
        ]));
        self::assertSame(['alice'], $this->names([
            ['event' => 'purchase', 'aggregate' => 'total_amount', 'operator' => '>', 'value' => 500],
        ]));
        self::assertSame(['alice', 'bob', 'dave'], $this->names([['event' => 'purchase']]));
    }

    public function testUnknownNamesEmptyAllButAreDroppedFromAny(): void
    {
        $conditions = [
            ['event' => 'signup'],
            ['event' => 'never_seen_event'],
            ['event' => 'purchase', 'property' => 'never_seen_property', 'operator' => '=', 'value' => 1],
        ];

        self::assertSame([], $this->names($conditions, 'all'));
        self::assertSame(['bob', 'carol'], $this->names($conditions, 'any'));
    }

    public function testKeysetPagination(): void
    {
        $query = fn (int $cursor) => $this->segmentation->query($this->validator->validate([
            'conditions' => [['event' => 'purchase']],
            'limit'      => 2,
            'cursor'     => $cursor,
        ]));

        // Other rows may exist in the table: start right before this dataset.
        $start = min($this->ids) - 1;

        $page1 = $query($start);
        self::assertSame([$this->ids['alice'], $this->ids['bob']], array_map(fn ($c) => $c->id, $page1->customers));
        self::assertSame($this->ids['bob'], $page1->nextCursor);

        $page2 = $query($page1->nextCursor);
        self::assertSame([$this->ids['dave']], array_map(fn ($c) => $c->id, $page2->customers));
        self::assertNull($page2->nextCursor);
        self::assertSame($page1->total, $page2->total, 'total is the whole segment size on every page');
    }

    /** The whole point: every branch is an index range read, never a full scan of a base table. */
    public function testQueryPlanUsesIndexesOnly(): void
    {
        $query = $this->validator->validate(['match' => 'all', 'conditions' => [
            ['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100],
            ['event' => 'purchase', 'property' => 'product', 'operator' => 'in', 'value' => ['Shoes', 'Hat']],
            ['event' => 'purchase', 'aggregate' => 'count', 'operator' => '>=', 'value' => 2],
        ]]);
        $eventTypeIds = (new EventTypeRepository($this->db))->findIds(['purchase']);
        $propertyKeyIds = (new PropertyKeyRepository($this->db))->findIds(['amount', 'product']);
        [$sql, $params] = $this->builder->build($query->conditions, $query->match, $eventTypeIds, $propertyKeyIds, 0, 101);

        $plan = $this->db->fetchAll("EXPLAIN $sql", $params);
        $baseTables = array_values(array_filter($plan, fn ($row) => !str_starts_with((string) $row['table'], '<')));

        self::assertCount(3, $baseTables);
        foreach ($baseTables as $row) {
            self::assertNotSame('ALL', $row['type'], "full scan on {$row['table']}");
            self::assertStringContainsString('Using index', (string) $row['Extra'], "{$row['table']} must be index-only");
        }
        self::assertSame(
            ['idx_ep_type_key_num', 'idx_ep_type_key_str', 'idx_stats_type_count'],
            array_column($baseTables, 'key'),
        );
    }
}
