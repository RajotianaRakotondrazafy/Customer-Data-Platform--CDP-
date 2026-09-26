<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Conversions between PHP dates and the storage / API formats. Everything is UTC.
 */
final class UtcDate
{
    private const DB_FORMAT = 'Y-m-d H:i:s.v';
    private const API_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    public static function fromDb(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, self::utc());
    }

    public static function fromDbNullable(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : self::fromDb($value);
    }

    public static function toDb(DateTimeInterface $date): string
    {
        return DateTimeImmutable::createFromInterface($date)->setTimezone(self::utc())->format(self::DB_FORMAT);
    }

    public static function toApi(?DateTimeInterface $date): ?string
    {
        return $date === null
            ? null
            : DateTimeImmutable::createFromInterface($date)->setTimezone(self::utc())->format(self::API_FORMAT);
    }

    private static ?DateTimeZone $utc = null;

    private static function utc(): DateTimeZone
    {
        return self::$utc ??= new DateTimeZone('UTC');
    }
}
