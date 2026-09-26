<?php

declare(strict_types=1);

/*
 * Seeds the database with a realistic dataset. WIPES ALL TABLES FIRST.
 *
 *   docker compose exec php composer seed
 *   docker compose exec php php database/seed.php --customers=50000 --events=1000000 --seed=7
 *
 * Defaults: 10,000 customers, 200,000 events, seed 42 (same seed = same data).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Kernel;
use App\Seed\DatabaseSeeder;

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$options = getopt('', ['customers::', 'events::', 'seed::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php database/seed.php [--customers=10000] [--events=200000] [--seed=42]\n";
    exit(0);
}

$customers = (int) ($options['customers'] ?? 10_000);
$events = (int) ($options['events'] ?? 200_000);
$seed = (int) ($options['seed'] ?? 42);

$kernel = Kernel::boot(dirname(__DIR__));
$start = microtime(true);

echo sprintf("Seeding %s customers / %s events (seed %d)...\n", number_format($customers), number_format($events), $seed);

try {
    $kernel->container()->get(DatabaseSeeder::class)->run(
        $customers,
        $events,
        $seed,
        static function (string $line): void {
            echo $line, "\n";
        },
    );
} catch (\Throwable $e) {
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo sprintf("Done in %.1fs. Demo API key: %s\n", microtime(true) - $start, DatabaseSeeder::DEMO_API_KEY);
