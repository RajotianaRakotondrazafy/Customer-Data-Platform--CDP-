<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\CustomerProfile;
use App\Repository\CustomerEventStatsRepository;
use App\Repository\CustomerRepository;
use App\Repository\EventRepository;

/**
 * Builds a customer profile with three indexed reads and no aggregation over the
 * event history: statistics come pre-computed from customer_event_stats.
 */
final class CustomerProfileService
{
    /** Event type whose count / total amount are reported as purchases / spend. */
    public const PURCHASE_EVENT = 'purchase';
    public const RECENT_EVENTS = 10;

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly EventRepository $events,
        private readonly CustomerEventStatsRepository $stats,
    ) {
    }

    public function get(int $customerId): ?CustomerProfile
    {
        $customer = $this->customers->find($customerId);
        if ($customer === null) {
            return null;
        }

        return new CustomerProfile(
            $customer,
            $this->events->latestForCustomer($customerId, self::RECENT_EVENTS),
            $this->stats->forCustomer($customerId),
            self::PURCHASE_EVENT,
        );
    }
}
