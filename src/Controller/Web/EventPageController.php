<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\CustomerRepository;
use App\Repository\EventRepository;

final class EventPageController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly View $view,
        private readonly EventRepository $events,
        private readonly CustomerRepository $customers,
    ) {
    }

    /** GET /events?before=<id> — latest events of all customers. */
    public function index(Request $request): Response
    {
        $before = max(0, (int) ($request->query['before'] ?? 0));

        $page = $this->events->latest(self::PER_PAGE + 1, $before);
        $hasMore = count($page) > self::PER_PAGE;
        $events = array_slice($page, 0, self::PER_PAGE);

        $emails = [];
        foreach ($this->customers->findByIds(array_values(array_unique(array_map(fn ($e) => $e->customerId, $events)))) as $customer) {
            $emails[$customer->id] = $customer->email;
        }

        return $this->view->render('events/index', [
            'title'      => 'Events',
            'active'     => 'events',
            'events'     => $events,
            'emails'     => $emails,
            'before'     => $before,
            'nextBefore' => $hasMore ? end($events)->id : null,
        ]);
    }
}
