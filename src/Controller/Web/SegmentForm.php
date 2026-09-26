<?php

declare(strict_types=1);

namespace App\Controller\Web;

/**
 * Maps the HTML segment form (GET query string, all values are strings) to the exact
 * JSON body of POST /api/segments/query, so the page goes through the same
 * validator and engine as the API.
 *
 * Form fields per row: conditions[i][kind|event|property|aggregate|operator|value]
 * with kind = property | aggregate | exists.
 */
final class SegmentForm
{
    /**
     * Rows to (re)display in the form; one empty row by default.
     *
     * @param array<string, mixed> $query
     * @return list<array<string, string>>
     */
    public static function rows(array $query): array
    {
        $rows = [];
        foreach (is_array($query['conditions'] ?? null) ? $query['conditions'] : [] as $row) {
            if (is_array($row)) {
                $rows[] = array_map(
                    fn ($v) => is_string($v) ? $v : '',
                    array_merge(
                        ['kind' => 'property', 'event' => '', 'property' => '', 'aggregate' => 'count', 'operator' => '>', 'value' => ''],
                        array_intersect_key($row, array_flip(['kind', 'event', 'property', 'aggregate', 'operator', 'value'])),
                    ),
                );
            }
        }

        return $rows ?: [['kind' => 'property', 'event' => 'purchase', 'property' => 'amount', 'aggregate' => 'count', 'operator' => '>', 'value' => '100']];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed> API request body.
     */
    public static function toApiBody(array $query): array
    {
        $conditions = [];
        foreach (self::rows($query) as $row) {
            $condition = ['event' => $row['event']];
            if ($row['kind'] === 'property') {
                $condition += ['property' => $row['property'], 'operator' => $row['operator'], 'value' => self::value($row)];
            } elseif ($row['kind'] === 'aggregate') {
                $condition += ['aggregate' => $row['aggregate'], 'operator' => $row['operator'], 'value' => self::value($row)];
            }
            $conditions[] = $condition;
        }

        $body = ['match' => ($query['match'] ?? 'all') === 'any' ? 'any' : 'all', 'conditions' => $conditions];
        if (isset($query['cursor']) && ctype_digit((string) $query['cursor'])) {
            $body['cursor'] = (int) $query['cursor'];
        }

        return $body;
    }

    /** @param array<string, string> $row */
    private static function value(array $row): mixed
    {
        return $row['operator'] === 'in'
            ? array_map(self::scalar(...), array_map('trim', explode(',', $row['value'])))
            : self::scalar(trim($row['value']));
    }

    /**
     * Types a form string like JSON would: 120 -> int, 9.99 -> float, true/false -> bool,
     * anything else -> string. Wrap in double quotes to force a string: "123".
     */
    private static function scalar(string $value): mixed
    {
        return match (true) {
            strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"') => substr($value, 1, -1),
            $value === 'true'  => true,
            $value === 'false' => false,
            (bool) preg_match('/^-?\d{1,15}$/', $value) => (int) $value,
            is_numeric($value) => (float) $value,
            default            => $value,
        };
    }
}
