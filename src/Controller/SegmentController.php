<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\Segmentation\SegmentationService;
use App\Validation\SegmentQueryValidator;

final class SegmentController
{
    public function __construct(
        private readonly SegmentQueryValidator $validator,
        private readonly SegmentationService $segmentation,
    ) {
    }

    /** POST /api/segments/query */
    public function query(Request $request): Response
    {
        return Response::json($this->segmentation->query($this->validator->validate($request->json())));
    }
}
