<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\ApiKeyRepository;
use Tests\Integration\DatabaseTestCase;

final class ApiKeyRepositoryTest extends DatabaseTestCase
{
    public function testActiveKeyIsFoundUntilRevoked(): void
    {
        $keys = new ApiKeyRepository($this->db);
        $hash = hash('sha256', 'test-secret-key', true);

        $id = $keys->create('Test source', $hash);
        $found = $keys->findActiveByHash($hash);

        self::assertSame($id, $found?->id);
        self::assertTrue($found->isActive());
        self::assertNull($keys->findActiveByHash(hash('sha256', 'wrong-key', true)));

        $keys->touch($id);
        self::assertNotNull($keys->findActiveByHash($hash)?->lastUsedAt);

        $keys->revoke($id);
        self::assertNull($keys->findActiveByHash($hash));
    }
}
