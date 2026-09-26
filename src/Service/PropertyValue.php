<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Single source of truth for how a property value maps to the typed columns of
 * event_properties. Used at ingestion (what is stored) AND by segment queries
 * (what is compared), so both sides always agree.
 */
final class PropertyValue
{
    public const MAX_STRING_LENGTH = 255;
    /** DECIMAL(20,6): 14 integer digits. */
    private const MAX_NUMBER = 1e14;

    /**
     * Numbers and booleans (1/0) become numeric strings — never floats, so DECIMAL
     * values are not rounded twice. Returns null when the value can't be indexed
     * (null, arrays, objects, too long strings, out of range numbers).
     *
     * @return array{bool, string}|null [is numeric, normalized value]
     */
    public static function normalize(mixed $value): ?array
    {
        return match (true) {
            is_bool($value)   => [true, $value ? '1' : '0'],
            is_int($value)    => abs($value) < self::MAX_NUMBER ? [true, (string) $value] : null,
            is_float($value)  => is_finite($value) && abs($value) < self::MAX_NUMBER
                ? [true, sprintf('%.6F', $value)]
                : null,
            is_string($value) => mb_strlen($value) <= self::MAX_STRING_LENGTH ? [false, $value] : null,
            default           => null,
        };
    }
}
