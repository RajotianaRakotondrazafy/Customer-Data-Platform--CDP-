<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\EventIngestionService;
use App\Validation\EventPayloadValidator;

final class EventController
{
    public function __construct(
        private readonly EventPayloadValidator $validator,
        private readonly EventIngestionService $ingestion,
    ) {
    }

    /**
     * POST /api/events
     * 201 Created on a new event, 200 OK on a replayed (deduplicated) one
     */
    public function store(Request $request): Response
    {
        $result = $this->ingestion->ingest($this->validator->validate($request->json()));

        return Response::json(['data' => $result], $result->duplicate ? 200 : 201);
    }
}
