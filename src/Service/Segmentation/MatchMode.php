<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/** How conditions combine: every condition (AND) or at least one (OR). */
enum MatchMode: string
{
    case All = 'all';
    case Any = 'any';
}
