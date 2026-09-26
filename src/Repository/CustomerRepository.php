<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Customer;

final class CustomerRepository extends AbstractRepository
{
    /**
     * Creates the customer or updates it, keyed by email. Returns the id in both cases,
     * in one round-trip: LAST_INSERT_ID(id) makes lastInsertId() return the existing id
     * on the update path. A null name never overwrites a known one.
     */
    public function upsert(string $email, ?string $name): int
    {
        $this->db->execute(
            'INSERT INTO customers (email, name) VALUES (?, ?) AS incoming
             ON DUPLICATE KEY UPDATE
                 name = COALESCE(incoming.name, customers.name),
                 id   = LAST_INSERT_ID(customers.id)',
            [$email, $name],
        );

        return $this->db->lastInsertId();
    }

    public function find(int $id): ?Customer
    {
        $row = $this->db->fetchOne(
            'SELECT id, email, name, created_at, updated_at FROM customers WHERE id = ?',
            [$id],
        );

        return $row === null ? null : Customer::fromRow($row);
    }

    public function findByEmail(string $email): ?Customer
    {
        $row = $this->db->fetchOne(
            'SELECT id, email, name, created_at, updated_at FROM customers WHERE email = ?',
            [$email],
        );

        return $row === null ? null : Customer::fromRow($row);
    }

    /**
     * @param list<int> $ids
     * @return list<Customer> Ordered by id.
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->fetchAll(
            sprintf(
                'SELECT id, email, name, created_at, updated_at FROM customers WHERE id IN (%s) ORDER BY id',
                self::placeholders(count($ids)),
            ),
            array_values($ids),
        );

        return array_map(Customer::fromRow(...), $rows);
    }

    /**
     * Keyset pagination (WHERE id > cursor): constant cost at any depth, unlike OFFSET.
     * Optional email prefix search (LIKE 'prefix%' can use uq_customers_email).
     *
     * @return list<Customer>
     */
    public function paginate(int $limit, int $afterId = 0, ?string $emailPrefix = null): array
    {
        $sql = 'SELECT id, email, name, created_at, updated_at FROM customers WHERE id > ?';
        $params = [$afterId];
        if ($emailPrefix !== null && $emailPrefix !== '') {
            $sql .= " AND email LIKE ? ESCAPE '!'";
            $params[] = strtr($emailPrefix, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        $params[] = $limit;

        return array_map(Customer::fromRow(...), $this->db->fetchAll("$sql ORDER BY id LIMIT ?", $params));
    }
}
