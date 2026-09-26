<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\CustomerRepository;
use Tests\Integration\DatabaseTestCase;

final class CustomerRepositoryTest extends DatabaseTestCase
{
    private CustomerRepository $customers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customers = new CustomerRepository($this->db);
    }

    public function testUpsertCreatesThenReturnsSameIdForSameEmail(): void
    {
        $id = $this->customers->upsert('john@example.com', 'John Doe');
        $again = $this->customers->upsert('john@example.com', 'Johnny');

        self::assertSame($id, $again);
        self::assertSame('Johnny', $this->customers->find($id)?->name);
    }

    public function testUpsertWithSameValuesStillReturnsId(): void
    {
        $id = $this->customers->upsert('same@example.com', 'Same');

        self::assertSame($id, $this->customers->upsert('same@example.com', 'Same'));
    }

    public function testNullNameDoesNotOverwriteKnownName(): void
    {
        $id = $this->customers->upsert('jane@example.com', 'Jane');
        $this->customers->upsert('jane@example.com', null);

        self::assertSame('Jane', $this->customers->find($id)?->name);
    }

    public function testEmailIsCaseInsensitive(): void
    {
        $id = $this->customers->upsert('case@example.com', 'Case');

        self::assertSame($id, $this->customers->upsert('CASE@Example.com', null));
    }

    public function testFindByIdsAndPaginate(): void
    {
        $a = $this->customers->upsert('a@example.com', 'A');
        $b = $this->customers->upsert('b@example.com', 'B');
        $c = $this->customers->upsert('c@example.com', 'C');

        self::assertSame([$a, $c], array_map(fn ($x) => $x->id, $this->customers->findByIds([$c, $a])));

        $page = $this->customers->paginate(2, $a);
        self::assertSame([$b, $c], array_map(fn ($x) => $x->id, $page));
    }

    public function testFindUnknownReturnsNull(): void
    {
        self::assertNull($this->customers->find(PHP_INT_MAX));
        self::assertSame([], $this->customers->findByIds([]));
    }
}
