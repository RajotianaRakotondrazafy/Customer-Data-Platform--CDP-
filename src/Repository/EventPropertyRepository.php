<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\EventProperty;

final class EventPropertyRepository extends AbstractRepository
{
    /**
     * Writes all properties of one event in a single multi-row INSERT.
     * customer_id / event_type_id are the denormalized copies that make the
     * segmentation indexes covering; they must match the parent event.
     *
     * @param list<EventProperty> $properties
     */
    public function insertMany(int $eventId, int $customerId, int $eventTypeId, array $properties): void
    {
        if ($properties === []) {
            return;
        }

        $params = [];
        foreach ($properties as $property) {
            array_push(
                $params,
                $eventId,
                $property->propertyKeyId,
                $customerId,
                $eventTypeId,
                $property->valueNum,
                $property->valueStr,
            );
        }

        $this->db->execute(
            'INSERT INTO event_properties
                 (event_id, property_key_id, customer_id, event_type_id, value_num, value_str)
             VALUES ' . implode(', ', array_fill(0, count($properties), '(?, ?, ?, ?, ?, ?)')),
            $params,
        );
    }
}
