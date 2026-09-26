<?php

declare(strict_types=1);

namespace App\Seed;

use App\Core\Database;
use App\Core\UtcDate;
use App\Model\IncomingEvent;
use App\Repository\ApiKeyRepository;
use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;
use App\Service\EventIngestionService;
use App\Service\PropertyValue;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Fills the database with a realistic, reproducible dataset (same --seed = same data).
 *
 * Bulk path instead of calling the ingestion service per event: multi-row INSERTs with
 * explicit ids, then customer_event_stats computed in one GROUP BY. It stays consistent
 * with real ingestion by reusing PropertyValue (typed columns) and the service's
 * dedup hash (replaying a seeded event through the API is detected as a duplicate).
 *
 * Dataset
 *   - every customer has a "signup" event at their first-seen date
 *   - other events are spread over the last 365 days, skewed so that a minority of
 *     customers are very active (like real traffic)
 *   - mix: page_view, email_open, add_to_cart, purchase, newsletter_signup
 */
final class DatabaseSeeder
{
    /** Plain key printed in the README; only its SHA-256 is stored. */
    public const DEMO_API_KEY = 'cdp_demo_key_change_me_0123456789';

    private const BATCH = 1000;
    /** Rows per event_properties INSERT: 6 params each, well under the 65,535 placeholder limit. */
    private const PROPERTY_BATCH = 5000;
    private const DAYS = 365;

    /** Event mix for non-signup events: name => weight. */
    private const EVENT_MIX = [
        'page_view'         => 55,
        'email_open'        => 17,
        'add_to_cart'       => 15,
        'purchase'          => 10,
        'newsletter_signup' => 3,
    ];

    /** name => [category, unit price] */
    private const PRODUCTS = [
        'Shoes'       => ['footwear', 89.90],
        'Sneakers'    => ['footwear', 120.00],
        'Boots'       => ['footwear', 159.00],
        'Hat'         => ['accessories', 24.50],
        'Scarf'       => ['accessories', 19.90],
        'Sunglasses'  => ['accessories', 75.00],
        'T-shirt'     => ['apparel', 15.00],
        'Jeans'       => ['apparel', 59.90],
        'Jacket'      => ['apparel', 199.00],
        'Backpack'    => ['bags', 64.00],
        'Watch'       => ['jewelry', 249.00],
        'Gift card'   => ['gift', 50.00],
    ];

    private const PAGES = ['/', '/products', '/products/shoes', '/products/jackets', '/cart', '/blog', '/about', '/sale'];
    private const SOURCES = ['google', 'facebook', 'instagram', 'newsletter', 'direct', 'tiktok'];
    private const CAMPAIGNS = ['spring_sale', 'summer_drop', 'black_friday', 'welcome_series', 'win_back'];
    private const COUPONS = ['SPRING10', 'WELCOME15', 'VIP20'];
    private const FIRST_NAMES = ['Emma', 'Louis', 'Jade', 'Gabriel', 'Alice', 'Hugo', 'Chloe', 'Arthur', 'Lina', 'Jules',
        'Rose', 'Adam', 'Mia', 'Noah', 'Lea', 'Tom', 'Zoe', 'Liam', 'Sarah', 'Nathan', 'Ines', 'Paul', 'Eva', 'Sacha'];
    private const LAST_NAMES = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy',
        'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'Rakoto', 'Rasoa', 'Andria', 'Roux', 'Fournier'];

    private DateTimeImmutable $now;
    /** @var array<string, int> */
    private array $typeIds = [];
    /** @var array<string, int> */
    private array $keyIds = [];

    public function __construct(
        private readonly Database $db,
        private readonly EventTypeRepository $eventTypes,
        private readonly PropertyKeyRepository $propertyKeys,
        private readonly ApiKeyRepository $apiKeys,
    ) {
    }

    /**
     * Wipes all tables, then seeds.
     *
     * @param callable(string): void $log
     */
    public function run(int $customers, int $events, int $seed, callable $log): void
    {
        if ($customers < 1 || $events < $customers) {
            throw new \InvalidArgumentException('Need at least 1 customer and events >= customers (one signup each).');
        }

        mt_srand($seed);
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->timed($log, 'Reset tables', fn () => $this->reset());
        $this->timed($log, 'Dictionaries', fn () => $this->dictionaries());
        $firstSeen = $this->timed($log, sprintf('%s customers', number_format($customers)), fn () => $this->customers($customers));
        $this->timed($log, sprintf('%s events + properties', number_format($events)), fn () => $this->events($firstSeen, $events, $log));
        $this->timed($log, 'customer_event_stats (GROUP BY)', fn () => $this->stats());
        $this->timed($log, 'Demo API key', fn () => $this->apiKeys->create('Demo key (seed)', hash('sha256', self::DEMO_API_KEY, true)));
        $this->timed($log, 'ANALYZE TABLE', fn () => $this->db->execute(
            'ANALYZE TABLE customers, events, event_properties, customer_event_stats',
        ));
    }

    private function reset(): void
    {
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['event_properties', 'customer_event_stats', 'events', 'customers', 'event_types', 'property_keys', 'api_keys'] as $table) {
            $this->db->execute("TRUNCATE TABLE $table");
        }
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function dictionaries(): void
    {
        $this->typeIds = $this->eventTypes->idsFor(['signup', ...array_keys(self::EVENT_MIX)]);
        $this->keyIds = $this->propertyKeys->idsFor([
            'source', 'page', 'referrer', 'campaign', 'product', 'category', 'price', 'quantity',
            EventIngestionService::AMOUNT_PROPERTY, 'currency', 'gift', 'coupon',
        ]);
    }

    /**
     * @return list<array{int, string, DateTimeImmutable}> [id, email, first seen] indexed from 0
     */
    private function customers(int $count): array
    {
        $customers = [];
        for ($id = 1; $id <= $count; $id++) {
            $first = self::pick(self::FIRST_NAMES);
            $last = self::pick(self::LAST_NAMES);
            $customers[] = [
                $id,
                sprintf('%s.%s.%d@example.com', strtolower($first), strtolower($last), $id),
                "$first $last",
                $this->randomDate($this->now->modify('-' . self::DAYS . ' days')),
            ];
        }

        foreach (array_chunk($customers, self::BATCH) as $chunk) {
            $params = [];
            foreach ($chunk as [$id, $email, $name, $at]) {
                array_push($params, $id, $email, $name, UtcDate::toDb($at), UtcDate::toDb($at));
            }
            $this->db->execute(
                'INSERT INTO customers (id, email, name, created_at, updated_at) VALUES '
                . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?)')),
                $params,
            );
        }

        return array_map(fn (array $c) => [$c[0], $c[1], $c[3]], $customers);
    }

    /**
     * @param list<array{int, string, DateTimeImmutable}> $customers
     * @param callable(string): void $log
     */
    private function events(array $customers, int $total, callable $log): void
    {
        $count = count($customers);
        $eventId = 0;
        $batch = [];
        $step = max(self::BATCH, intdiv($total, 10));

        for ($i = 0; $i < $total; $i++) {
            if ($i < $count) {
                // One signup per customer, at their first-seen date.
                [$customerId, $email, $at] = $customers[$i];
                $event = 'signup';
                $properties = ['source' => self::pick(self::SOURCES)];
            } else {
                // Skewed pick: r^3 concentrates activity on a minority of customers.
                [$customerId, $email, $firstSeen] = $customers[(int) ($count * (mt_rand() / (mt_getrandmax() + 1)) ** 3)];
                $event = self::weighted(self::EVENT_MIX);
                $properties = $this->properties($event);
                $at = $this->randomDate($firstSeen);
            }

            $batch[] = [++$eventId, $customerId, $email, $event, $properties, $at];

            if (count($batch) === self::BATCH) {
                $this->flushEvents($batch);
                $batch = [];
            }
            if (($i + 1) % $step === 0) {
                $log(sprintf('      ... %s / %s events', number_format($i + 1), number_format($total)));
            }
        }
        if ($batch !== []) {
            $this->flushEvents($batch);
        }
    }

    /** @param list<array{int, int, string, string, array<string, mixed>, DateTimeImmutable}> $batch */
    private function flushEvents(array $batch): void
    {
        $eventParams = [];
        $propertyRows = [];

        foreach ($batch as [$eventId, $customerId, $email, $event, $properties, $at]) {
            $typeId = $this->typeIds[$event];
            array_push(
                $eventParams,
                $eventId,
                $customerId,
                $typeId,
                UtcDate::toDb($at),
                UtcDate::toDb($at),
                $properties === [] ? null : json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                EventIngestionService::dedupHash(new IncomingEvent($email, null, $event, $properties, $at)),
            );

            foreach ($properties as $key => $value) {
                $normalized = PropertyValue::normalize($value);
                if ($normalized !== null) {
                    [$numeric, $v] = $normalized;
                    $propertyRows[] = [$eventId, $this->keyIds[$key], $customerId, $typeId, $numeric ? $v : null, $numeric ? null : $v];
                }
            }
        }

        $this->db->transaction(function () use ($batch, $eventParams, $propertyRows): void {
            $this->db->execute(
                'INSERT INTO events (id, customer_id, event_type_id, occurred_at, received_at, properties, dedup_hash) VALUES '
                . implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?)')),
                $eventParams,
            );
            foreach (array_chunk($propertyRows, self::PROPERTY_BATCH) as $chunk) {
                $this->db->execute(
                    'INSERT INTO event_properties (event_id, property_key_id, customer_id, event_type_id, value_num, value_str) VALUES '
                    . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?)')),
                    array_merge(...$chunk),
                );
            }
        });
    }

    /** Same aggregates the ingestion service maintains incrementally, computed in one pass. */
    private function stats(): void
    {
        $this->db->execute(
            'INSERT INTO customer_event_stats (customer_id, event_type_id, event_count, total_amount, first_at, last_at)
             SELECT e.customer_id, e.event_type_id, COUNT(*), COALESCE(SUM(p.value_num), 0), MIN(e.occurred_at), MAX(e.occurred_at)
             FROM events e
             LEFT JOIN event_properties p ON p.event_id = e.id AND p.property_key_id = ?
             GROUP BY e.customer_id, e.event_type_id',
            [$this->keyIds[EventIngestionService::AMOUNT_PROPERTY]],
        );
    }

    /** @return array<string, mixed> */
    private function properties(string $event): array
    {
        return match ($event) {
            'page_view' => ['page' => self::pick(self::PAGES), 'referrer' => self::pick(self::SOURCES)],
            'email_open' => ['campaign' => self::pick(self::CAMPAIGNS)],
            'newsletter_signup' => ['source' => self::pick(self::SOURCES)],
            'add_to_cart' => (function (): array {
                $product = self::pick(array_keys(self::PRODUCTS));

                return ['product' => $product, 'price' => self::PRODUCTS[$product][1], 'quantity' => mt_rand(1, 3)];
            })(),
            'purchase' => (function (): array {
                $product = self::pick(array_keys(self::PRODUCTS));
                [$category, $price] = self::PRODUCTS[$product];
                $quantity = mt_rand(1, 3);
                $properties = [
                    'amount'   => round($price * $quantity, 2),
                    'product'  => $product,
                    'category' => $category,
                    'quantity' => $quantity,
                    'currency' => 'EUR',
                    'gift'     => mt_rand(1, 10) === 1,
                ];
                if (mt_rand(1, 100) <= 15) {
                    $properties['coupon'] = self::pick(self::COUPONS);
                }

                return $properties;
            })(),
            default => [],
        };
    }

    private function randomDate(DateTimeImmutable $from): DateTimeImmutable
    {
        $start = (int) $from->format('Uv');
        $end = (int) $this->now->format('Uv');
        $ms = mt_rand($start, max($start, $end));

        return (new DateTimeImmutable('@' . intdiv($ms, 1000)))
            ->modify(sprintf('+%d milliseconds', $ms % 1000))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @template T
     * @param list<T> $items
     * @return T
     */
    private static function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    /** @param array<string, int> $weights */
    private static function weighted(array $weights): string
    {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $name => $weight) {
            if (($r -= $weight) <= 0) {
                return $name;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @template T
     * @param callable(string): void $log
     * @param callable(): T $step
     * @return T
     */
    private function timed(callable $log, string $label, callable $step): mixed
    {
        $start = microtime(true);
        $result = $step();
        $log(sprintf('  ✔ %-40s %7.2fs', $label, microtime(true) - $start));

        return $result;
    }
}
