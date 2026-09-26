<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

use App\Model\SegmentResult;
use App\Repository\CustomerRepository;
use App\Repository\EventTypeRepository;
use App\Repository\PropertyKeyRepository;
use App\Repository\SegmentRepository;

final class SegmentationService
{
    public function __construct(
        private readonly SegmentRepository $segments,
        private readonly CustomerRepository $customers,
        private readonly EventTypeRepository $eventTypes,
        private readonly PropertyKeyRepository $propertyKeys,
    ) {
    }

    public function query(SegmentQuery $query): SegmentResult
    {
        // Resolve names without creating them: an unknown event or property can't match anything.
        $eventTypeIds = $this->eventTypes->findIds(
            array_map(fn (Condition $c) => $c->event(), $query->conditions),
        );
        $propertyKeyIds = $this->propertyKeys->findIds(array_map(
            fn (PropertyCondition $c) => $c->property,
            array_values(array_filter($query->conditions, fn (Condition $c) => $c instanceof PropertyCondition)),
        ));

        $resolvable = array_values(array_filter(
            $query->conditions,
            fn (Condition $c) => isset($eventTypeIds[$c->event()])
                && (!$c instanceof PropertyCondition || isset($propertyKeyIds[$c->property])),
        ));

        // all: one impossible condition empties the segment -> answered without any query.
        // any: impossible conditions are simply dropped.
        if ($resolvable === []
            || ($query->match === MatchMode::All && count($resolvable) < count($query->conditions))) {
            return SegmentResult::empty($query->limit);
        }

        // Ask one extra row to know whether a next page exists.
        $page = $this->segments->findCustomerIds(
            $resolvable,
            $query->match,
            $eventTypeIds,
            $propertyKeyIds,
            $query->cursor,
            $query->limit + 1,
        );

        $ids = array_slice($page['ids'], 0, $query->limit);
        $hasMore = count($page['ids']) > $query->limit;

        return new SegmentResult(
            $this->customers->findByIds($ids),
            $page['total'],
            $query->limit,
            $hasMore ? end($ids) : null,
        );
    }
}
