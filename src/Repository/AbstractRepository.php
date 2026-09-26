<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

abstract class AbstractRepository
{
    public function __construct(protected readonly Database $db)
    {
    }

    /** "?, ?, ?" for an IN (...) list of $count values. */
    protected static function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, $count, '?'));
    }
}
