<?php

declare(strict_types=1);

namespace App\Model;

/**
 * One typed property value, as written to event_properties.
 * Exactly one of $valueNum / $valueStr is set. Numbers are kept as numeric strings
 * so DECIMAL values never go through float rounding.
 */
final readonly class EventProperty
{
    private function __construct(
        public int $propertyKeyId,
        public ?string $valueNum,
        public ?string $valueStr,
    ) {
    }

    public static function numeric(int $propertyKeyId, int|float|string $value): self
    {
        return new self($propertyKeyId, (string) $value, null);
    }

    public static function string(int $propertyKeyId, string $value): self
    {
        return new self($propertyKeyId, null, $value);
    }
}
