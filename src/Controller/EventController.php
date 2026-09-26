<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

final class EventController
{
    /** POST /api/events */
    public function store(Request $request): Response
    {
        // TODO: EventPayloadValidator -> EventIngestionService::ingest() -> 201
        return Response::error(501, 'Not implemented yet.');
    }
}
