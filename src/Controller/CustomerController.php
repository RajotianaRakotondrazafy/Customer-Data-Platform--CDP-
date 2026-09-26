<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

final class CustomerController
{
    /** GET /api/customers/{id} */
    public function show(Request $request): Response
    {
        // TODO: CustomerProfileService::get((int) $request->route('id')) -> customer + last 10 events + stats
        return Response::error(501, 'Not implemented yet.');
    }
}
