<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/** Pre-computed per-customer aggregates available in customer_event_stats. */
enum Aggregate: string
{
    case Count = 'count';
    case TotalAmount = 'total_amount';

    public function column(): string
    {
        return match ($this) {
            self::Count       => 'event_count',
            self::TotalAmount => 'total_amount',
        };
    }

    public function index(): string
    {
        return match ($this) {
            self::Count       => 'idx_stats_type_count',
            self::TotalAmount => 'idx_stats_type_amount',
        };
    }
}
