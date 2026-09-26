<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ApiKey;

/**
 * Only SHA-256 hashes (raw 32 bytes) are stored and looked up; hashing the
 * plain key is the caller's job (see ApiKeyMiddleware).
 */
final class ApiKeyRepository extends AbstractRepository
{
    public function create(string $name, string $keyHash): int
    {
        $this->db->execute('INSERT INTO api_keys (name, key_hash) VALUES (?, ?)', [$name, $keyHash]);

        return $this->db->lastInsertId();
    }

    public function findActiveByHash(string $keyHash): ?ApiKey
    {
        $row = $this->db->fetchOne(
            'SELECT id, name, created_at, last_used_at, revoked_at
             FROM api_keys
             WHERE key_hash = ? AND revoked_at IS NULL',
            [$keyHash],
        );

        return $row === null ? null : ApiKey::fromRow($row);
    }

    public function touch(int $id): void
    {
        $this->db->execute('UPDATE api_keys SET last_used_at = NOW(3) WHERE id = ?', [$id]);
    }

    public function revoke(int $id): void
    {
        $this->db->execute('UPDATE api_keys SET revoked_at = NOW(3) WHERE id = ? AND revoked_at IS NULL', [$id]);
    }
}
