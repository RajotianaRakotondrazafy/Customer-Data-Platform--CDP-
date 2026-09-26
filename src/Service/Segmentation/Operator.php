<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

enum Operator: string
{
    case Eq = '=';
    case Neq = '!=';
    case Gt = '>';
    case Gte = '>=';
    case Lt = '<';
    case Lte = '<=';
    case In = 'in';
    case Contains = 'contains';
    case StartsWith = 'starts_with';

    /** Operators that only make sense on strings (LIKE-based). */
    public function isStringOnly(): bool
    {
        return $this === self::Contains || $this === self::StartsWith;
    }

    /** SQL comparison for the scalar operators. */
    public function sql(): string
    {
        return match ($this) {
            self::Eq  => '=',
            self::Neq => '<>',
            self::Gt  => '>',
            self::Gte => '>=',
            self::Lt  => '<',
            self::Lte => '<=',
            default   => throw new \LogicException("$this->value is not a scalar comparison."),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $op) => $op->value, self::cases());
    }
}
