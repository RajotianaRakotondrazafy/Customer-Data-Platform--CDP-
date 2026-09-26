<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

final class SegmentController
{
    /** POST /api/segments/query */
    public function query(Request $request): Response
    {
        // TODO: parse conditions -> SegmentQueryBuilder -> CustomerRepository -> matching customers
        return Response::error(501, 'Not implemented yet.');
    }
}
