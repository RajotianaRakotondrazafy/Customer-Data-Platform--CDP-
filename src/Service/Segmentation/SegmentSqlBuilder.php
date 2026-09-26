<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/**
 * Turns segment conditions into ONE parameterized SQL query returning a page of
 * matching customer ids plus the segment size.
 *
 * Strategy
 *   1. Each condition becomes a branch that returns the DISTINCT customer_ids
 *      matching it, read from a covering index only (no table rows, no full scan):
 *        - PropertyCondition  -> event_properties  idx_ep_type_key_num / _str
 *                                range on (event_type_id, property_key_id, value)
 *        - AggregateCondition -> customer_event_stats idx_stats_type_count / _amount
 *                                range on (event_type_id, aggregate)
 *   2. Branches are combined with UNION ALL + GROUP BY customer_id:
 *        all -> HAVING COUNT(*) = <number of branches>  (intersection)
 *        any -> no HAVING                               (union)
 *      This fixed shape keeps the plan predictable: every branch is evaluated
 *      once, whereas "id IN (...) OR id IN (...)" pushes MySQL towards dependent
 *      subqueries executed per customer.
 *   3. COUNT(*) OVER () gives the total segment size in the same pass, then keyset
 *      pagination (customer_id > cursor) returns one page.
 *
 * FORCE INDEX pins the index each branch was designed for, so the plan doesn't
 * depend on table statistics (e.g. a PK scan chosen on small tables).
 *
 * All values are bound parameters; identifiers (columns, indexes, operators) only
 * ever come from enums, never from user input.
 */
final class SegmentSqlBuilder
{
    private const DECIMAL = 'CAST(? AS DECIMAL(20,6))';

    /**
     * @param list<Condition>    $conditions     All names must be resolvable in the maps.
     * @param array<string, int> $eventTypeIds   event name => id
     * @param array<string, int> $propertyKeyIds property name => id
     * @param int                $limit          Rows to return (callers ask limit + 1 to detect a next page).
     * @return array{string, list<int|string>} [sql, params]
     */
    public function build(
        array $conditions,
        MatchMode $match,
        array $eventTypeIds,
        array $propertyKeyIds,
        int $cursor,
        int $limit,
    ): array {
        if ($conditions === []) {
            throw new \InvalidArgumentException('At least one condition is required.');
        }

        $branches = [];
        $params = [];
        foreach ($conditions as $condition) {
            [$sql, $branchParams] = match (true) {
                $condition instanceof PropertyCondition => $this->propertyBranch(
                    $condition,
                    $eventTypeIds[$condition->event],
                    $propertyKeyIds[$condition->property],
                ),
                $condition instanceof AggregateCondition => $this->aggregateBranch(
                    $condition,
                    $eventTypeIds[$condition->event],
                ),
                default => throw new \InvalidArgumentException('Unsupported condition ' . $condition::class),
            };
            $branches[] = $sql;
            array_push($params, ...$branchParams);
        }

        $having = '';
        if ($match === MatchMode::All && count($branches) > 1) {
            $having = 'HAVING COUNT(*) = ?';
            $params[] = count($branches);
        }
        array_push($params, $cursor, $limit);

        $union = implode("\n        UNION ALL\n        ", $branches);
        $sql = <<<SQL
            SELECT customer_id, total
            FROM (
                SELECT customer_id, COUNT(*) OVER () AS total
                FROM (
                    $union
                ) AS matches
                GROUP BY customer_id
                $having
            ) AS segment
            WHERE customer_id > ?
            ORDER BY customer_id
            LIMIT ?
            SQL;

        return [$sql, $params];
    }

    /** @return array{string, list<int|string>} */
    private function propertyBranch(PropertyCondition $c, int $eventTypeId, int $propertyKeyId): array
    {
        [$column, $index] = $c->numeric
            ? ['value_num', 'idx_ep_type_key_num']
            : ['value_str', 'idx_ep_type_key_str'];
        $placeholder = $c->numeric ? self::DECIMAL : '?';

        [$predicate, $values] = match ($c->operator) {
            Operator::In => [
                sprintf('%s IN (%s)', $column, implode(', ', array_fill(0, count($c->values), $placeholder))),
                $c->values,
            ],
            Operator::Contains => [
                "$column LIKE ? ESCAPE '!'",
                ['%' . self::escapeLike($c->values[0]) . '%'],
            ],
            Operator::StartsWith => [
                "$column LIKE ? ESCAPE '!'",
                [self::escapeLike($c->values[0]) . '%'],
            ],
            default => [
                sprintf('%s %s %s', $column, $c->operator->sql(), $placeholder),
                [$c->values[0]],
            ],
        };

        return [
            "SELECT DISTINCT customer_id FROM event_properties FORCE INDEX ($index)"
            . " WHERE event_type_id = ? AND property_key_id = ? AND $predicate",
            [$eventTypeId, $propertyKeyId, ...$values],
        ];
    }

    /** @return array{string, list<int|string>} */
    private function aggregateBranch(AggregateCondition $c, int $eventTypeId): array
    {
        $placeholder = $c->aggregate === Aggregate::Count ? '?' : self::DECIMAL;

        return [
            sprintf(
                'SELECT customer_id FROM customer_event_stats FORCE INDEX (%s) WHERE event_type_id = ? AND %s %s %s',
                $c->aggregate->index(),
                $c->aggregate->column(),
                $c->operator->sql(),
                $placeholder,
            ),
            // customer_event_stats has one row per (customer, type): no DISTINCT needed.
            [$eventTypeId, $c->value],
        ];
    }

    /** Escapes LIKE wildcards so "50%" matches literally ("!" is the escape char). */
    private static function escapeLike(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
