<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/** A segment condition; every condition targets one event type. */
interface Condition
{
    public function event(): string;
}
