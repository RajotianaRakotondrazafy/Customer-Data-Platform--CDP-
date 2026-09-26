<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;
use Tests\Integration\DatabaseTestCase;

final class DictionaryRepositoryTest extends DatabaseTestCase
{
    public function testIdsForCreatesMissingAndReusesExisting(): void
    {
        $types = new EventTypeRepository($this->db);

        $first = $types->idsFor(['test_purchase', 'test_view']);
        self::assertCount(2, $first);

        // Fresh instance: no cache, must read back the same ids from the table.
        $second = (new EventTypeRepository($this->db))->idsFor(['test_view', 'TEST_PURCHASE']);
        self::assertSame($first['test_purchase'], $second['test_purchase']);
        self::assertSame($first['test_view'], $second['test_view']);
    }

    public function testFindIdDoesNotCreate(): void
    {
        $keys = new PropertyKeyRepository($this->db);

        self::assertNull($keys->findId('test_never_created'));
        self::assertNull($keys->findId('test_never_created'));

        $id = $keys->idFor('test_amount');
        self::assertSame($id, $keys->findId('Test_Amount'));
    }
}
