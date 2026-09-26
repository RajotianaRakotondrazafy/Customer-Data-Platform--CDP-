<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\CustomerEventStatsRepository;
use App\Repository\CustomerRepository;
use App\Service\CustomerProfileService;

final class CustomerPageController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly View $view,
        private readonly CustomerRepository $customers,
        private readonly CustomerEventStatsRepository $stats,
        private readonly CustomerProfileService $profiles,
    ) {
    }

    /** GET / */
    public function home(Request $request): Response
    {
        return View::redirect('/customers');
    }

    /** GET /customers?q=<email prefix>&cursor=<id> */
    public function index(Request $request): Response
    {
        $search = trim((string) ($request->query['q'] ?? ''));
        $cursor = max(0, (int) ($request->query['cursor'] ?? 0));

        $page = $this->customers->paginate(self::PER_PAGE + 1, $cursor, mb_strtolower($search));
        $hasMore = count($page) > self::PER_PAGE;
        $customers = array_slice($page, 0, self::PER_PAGE);

        return $this->view->render('customers/index', [
            'title'      => 'Customers',
            'active'     => 'customers',
            'customers'  => $customers,
            'totals'     => $this->stats->totalsFor(
                array_map(fn ($c) => $c->id, $customers),
                CustomerProfileService::PURCHASE_EVENT,
            ),
            'search'     => $search,
            'cursor'     => $cursor,
            'nextCursor' => $hasMore ? end($customers)->id : null,
        ]);
    }

    /** GET /customers/{id} */
    public function show(Request $request): Response
    {
        $profile = $this->profiles->get((int) $request->route('id'));
        if ($profile === null) {
            return $this->view->render('error', ['title' => 'Not found', 'message' => 'Customer not found.'], 404);
        }

        return $this->view->render('customers/show', [
            'title'   => $profile->customer->email,
            'active'  => 'customers',
            'profile' => $profile->jsonSerialize(),
        ]);
    }
}
