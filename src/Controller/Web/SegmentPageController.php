<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Core\Exception\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;
use App\Service\Segmentation\Operator;
use App\Service\Segmentation\SegmentationService;
use App\Validation\SegmentQueryValidator;

final class SegmentPageController
{
    public function __construct(
        private readonly View $view,
        private readonly SegmentQueryValidator $validator,
        private readonly SegmentationService $segmentation,
        private readonly EventTypeRepository $eventTypes,
        private readonly PropertyKeyRepository $propertyKeys,
    ) {
    }

    /** GET /segments — condition builder; runs the query when the form is submitted. */
    public function index(Request $request): Response
    {
        $submitted = isset($request->query['conditions']);
        $body = SegmentForm::toApiBody($request->query);
        $result = null;
        $errors = [];
        $ms = null;

        if ($submitted) {
            try {
                $start = hrtime(true);
                $result = $this->segmentation->query($this->validator->validate($body));
                $ms = (hrtime(true) - $start) / 1e6;
            } catch (ValidationException $e) {
                $errors = $e->errors;
            }
        }

        unset($body['cursor']);

        return $this->view->render('segments/index', [
            'title'      => 'Segments',
            'active'     => 'segments',
            'rows'       => SegmentForm::rows($request->query),
            'operators'  => Operator::values(),
            'eventNames' => $this->eventTypes->names(),
            'keyNames'   => $this->propertyKeys->names(),
            'match'      => $body['match'],
            'query'      => $request->query,
            'apiBody'    => $body,
            'result'     => $result,
            'errors'     => $errors,
            'ms'         => $ms,
        ]);
    }
}
