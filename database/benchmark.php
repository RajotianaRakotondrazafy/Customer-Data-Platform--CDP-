<?php

declare(strict_types=1);

/*
 * Times segment queries and the profile endpoint on the current data (run the seed first):
 *
 *   docker compose exec php composer benchmark
 *
 * Each query runs 5 times; the median is reported. The spec example is also run the
 * naive way (JSON_EXTRACT over every event) to compare with the indexed strategy and
 * to cross-check that both return the same segment size.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Kernel;
use App\Service\CustomerProfileService;
use App\Service\Segmentation\SegmentationService;
use App\Validation\SegmentQueryValidator;

$container = Kernel::boot(dirname(__DIR__))->container();
$db = $container->get(Database::class);
$segmentation = $container->get(SegmentationService::class);
$validator = $container->get(SegmentQueryValidator::class);
$profiles = $container->get(CustomerProfileService::class);

/** @return array{float, mixed} median ms, last result */
$measure = static function (callable $fn, int $runs = 5): array {
    $times = [];
    $result = null;
    for ($i = 0; $i < $runs; $i++) {
        $start = hrtime(true);
        $result = $fn();
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    sort($times);

    return [$times[intdiv($runs, 2)], $result];
};

$counts = $db->fetchOne(
    'SELECT (SELECT COUNT(*) FROM customers) customers, (SELECT COUNT(*) FROM events) events,
            (SELECT COUNT(*) FROM event_properties) properties',
);
printf(
    "Dataset: %s customers, %s events, %s indexed properties\n\n",
    number_format($counts['customers']),
    number_format($counts['events']),
    number_format($counts['properties']),
);

$queries = [
    'Spec: purchase.amount > 100' => [
        'conditions' => [['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100]],
    ],
    'Selective: purchase.amount > 700' => [
        'conditions' => [['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 700]],
    ],
    'product in [Watch, Jacket]' => [
        'conditions' => [['event' => 'purchase', 'property' => 'product', 'operator' => 'in', 'value' => ['Watch', 'Jacket']]],
    ],
    'all: amount>100 + coupon + 3 purchases' => [
        'conditions' => [
            ['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100],
            ['event' => 'purchase', 'property' => 'coupon', 'operator' => 'starts_with', 'value' => 'VIP'],
            ['event' => 'purchase', 'aggregate' => 'count', 'operator' => '>=', 'value' => 3],
        ],
    ],
    'any: newsletter OR spent >= 1000' => [
        'match'      => 'any',
        'conditions' => [
            ['event' => 'newsletter_signup'],
            ['event' => 'purchase', 'aggregate' => 'total_amount', 'operator' => '>=', 'value' => 1000],
        ],
    ],
    'page_view.page contains "shoes"' => [
        'conditions' => [['event' => 'page_view', 'property' => 'page', 'operator' => 'contains', 'value' => 'shoes']],
    ],
];

printf("%-42s %12s %10s\n", 'Segment query (first page of 100)', 'segment size', 'median');
printf("%s\n", str_repeat('-', 66));
$totals = [];
foreach ($queries as $label => $body) {
    $query = $validator->validate($body);
    [$ms, $result] = $measure(fn () => $segmentation->query($query));
    $totals[$label] = $result->total;
    printf("%-42s %12s %8.1fms\n", $label, number_format($result->total), $ms);
}

// Naive baseline: filter the raw JSON of every event (what the typed index avoids).
// Its cost is the same whatever the threshold; the indexed query only reads matching entries.
printf("\n%-42s %12s %10s\n", 'Naive baseline (JSON_EXTRACT on all events)', '', '');
printf("%s\n", str_repeat('-', 66));
foreach (['Spec: purchase.amount > 100' => 100, 'Selective: purchase.amount > 700' => 700] as $label => $threshold) {
    [$ms, $naive] = $measure(fn () => $db->fetchOne(
        "SELECT COUNT(DISTINCT e.customer_id) AS n
         FROM events e JOIN event_types t ON t.id = e.event_type_id
         WHERE t.name = 'purchase' AND JSON_EXTRACT(e.properties, '$.amount') > ?",
        [$threshold],
    ), 3);
    printf("%-42s %12s %8.1fms\n", "naive amount > $threshold", number_format((int) $naive['n']), $ms);
    if ((int) $naive['n'] !== $totals[$label]) {
        printf("  !! MISMATCH with indexed result: %d vs %d\n", $totals[$label], $naive['n']);
    }
}

// Profile of the most active customer (the worst case for "last 10 events").
$top = $db->fetchOne('SELECT customer_id, SUM(event_count) n FROM customer_event_stats GROUP BY customer_id ORDER BY n DESC LIMIT 1');
[$ms] = $measure(fn () => $profiles->get((int) $top['customer_id']));
printf("\n%-42s %12s %8.1fms\n", sprintf('Profile of top customer (%s events)', number_format((int) $top['n'])), '', $ms);
