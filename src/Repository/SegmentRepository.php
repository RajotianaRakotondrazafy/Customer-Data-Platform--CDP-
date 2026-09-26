<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Service\Segmentation\Condition;
use App\Service\Segmentation\MatchMode;
use App\Service\Segmentation\SegmentSqlBuilder;

final class SegmentRepository extends AbstractRepository
{
    public function __construct(Database $db, private readonly SegmentSqlBuilder $builder)
    {
        parent::__construct($db);
    }

    /**
     * @param list<Condition>    $conditions
     * @param array<string, int> $eventTypeIds
     * @param array<string, int> $propertyKeyIds
     * @return array{ids: list<int>, total: int} One page of ids (ascending) and the segment size.
     */
    public function findCustomerIds(
        array $conditions,
        MatchMode $match,
        array $eventTypeIds,
        array $propertyKeyIds,
        int $cursor,
        int $limit,
    ): array {
        [$sql, $params] = $this->builder->build($conditions, $match, $eventTypeIds, $propertyKeyIds, $cursor, $limit);
        $rows = $this->db->fetchAll($sql, $params);

        return [
            'ids'   => array_map(fn (array $r) => (int) $r['customer_id'], $rows),
            'total' => $rows === [] ? 0 : (int) $rows[0]['total'],
        ];
    }
}
