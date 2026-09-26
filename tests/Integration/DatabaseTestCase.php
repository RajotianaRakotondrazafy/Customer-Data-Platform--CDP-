<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Runs each test against the real MySQL inside a transaction rolled back afterwards.
 * Skipped when the database is unreachable (e.g. running outside Docker).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Database $db;

    protected function setUp(): void
    {
        $this->db = new Database([
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => (int) (getenv('DB_PORT') ?: 3306),
            'name'     => getenv('DB_NAME') ?: 'cdp',
            'user'     => getenv('DB_USER') ?: 'cdp',
            'password' => getenv('DB_PASSWORD') ?: 'cdp',
        ]);

        try {
            $this->db->pdo()->beginTransaction();
        } catch (\PDOException $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }
}
