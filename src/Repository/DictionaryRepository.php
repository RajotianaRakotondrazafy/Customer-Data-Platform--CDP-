<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Name <-> SMALLINT id lookup tables (event_types, property_keys), with a per-request cache.
 *
 * Get-or-create does SELECT first and INSERT only for missing names: an
 * "INSERT ... ON DUPLICATE KEY" on every call would burn an AUTO_INCREMENT
 * value each time (InnoDB), and a SMALLINT id space would run out quickly.
 *
 * Names are matched case-insensitively (the column collation is *_ai_ci),
 * so the cache is keyed by lower-cased name.
 */
abstract class DictionaryRepository extends AbstractRepository
{
    /** @var array<string, int> lower-cased name => id */
    private array $cache = [];

    abstract protected function table(): string;

    /** Id of an existing name, without creating it (e.g. segment queries). */
    public function findId(string $name): ?int
    {
        return $this->findIds([$name])[mb_strtolower($name)] ?? null;
    }

    /**
     * Ids of existing names only.
     *
     * @param list<string> $names
     * @return array<string, int> lower-cased name => id
     */
    public function findIds(array $names): array
    {
        $keys = array_values(array_unique(array_map('mb_strtolower', $names)));
        $missing = array_values(array_diff($keys, array_keys($this->cache)));

        if ($missing !== []) {
            $rows = $this->db->fetchAll(
                sprintf('SELECT id, name FROM %s WHERE name IN (%s)', $this->table(), self::placeholders(count($missing))),
                $missing,
            );
            foreach ($rows as $row) {
                $this->cache[mb_strtolower($row['name'])] = (int) $row['id'];
            }
        }

        return array_intersect_key($this->cache, array_flip($keys));
    }

    /**
     * Ids for all names, creating the missing ones.
     *
     * @param list<string> $names
     * @return array<string, int> lower-cased name => id
     */
    public function idsFor(array $names): array
    {
        $keys = array_values(array_unique(array_map('mb_strtolower', $names)));
        $ids = $this->findIds($keys);
        $missing = array_values(array_diff($keys, array_keys($ids)));

        if ($missing === []) {
            return $ids;
        }

        // Race-safe: a concurrent request may insert the same name; "id = id" makes it a no-op.
        $this->db->execute(
            sprintf(
                'INSERT INTO %s (name) VALUES %s ON DUPLICATE KEY UPDATE id = id',
                $this->table(),
                implode(', ', array_fill(0, count($missing), '(?)')),
            ),
            $missing,
        );

        return $ids + $this->findIds($missing);
    }

    /** @return list<string> All names, alphabetically (dictionaries are small). */
    public function names(): array
    {
        return array_column($this->db->fetchAll(sprintf('SELECT name FROM %s ORDER BY name', $this->table())), 'name');
    }

    public function idFor(string $name): int
    {
        return $this->idsFor([$name])[mb_strtolower($name)];
    }
}
