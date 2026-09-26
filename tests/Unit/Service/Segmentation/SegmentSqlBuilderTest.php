<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Segmentation;

use App\Service\Segmentation\Aggregate;
use App\Service\Segmentation\AggregateCondition;
use App\Service\Segmentation\MatchMode;
use App\Service\Segmentation\Operator;
use App\Service\Segmentation\PropertyCondition;
use App\Service\Segmentation\SegmentSqlBuilder;
use PHPUnit\Framework\TestCase;

final class SegmentSqlBuilderTest extends TestCase
{
    private const EVENTS = ['purchase' => 1, 'signup' => 2];
    private const KEYS = ['amount' => 10, 'product' => 11];

    private SegmentSqlBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SegmentSqlBuilder();
    }

    public function testSingleNumericConditionUsesCoveringIndexWithoutHaving(): void
    {
        [$sql, $params] = $this->builder->build(
            [new PropertyCondition('purchase', 'amount', Operator::Gt, true, ['100'])],
            MatchMode::All,
            self::EVENTS,
            self::KEYS,
            0,
            51,
        );

        self::assertStringContainsString('FROM event_properties FORCE INDEX (idx_ep_type_key_num)', $sql);
        self::assertStringContainsString('value_num > CAST(? AS DECIMAL(20,6))', $sql);
        self::assertStringNotContainsString('HAVING', $sql);
        self::assertSame([1, 10, '100', 0, 51], $params);
    }

    public function testAllCombinesBranchesWithHavingCount(): void
    {
        [$sql, $params] = $this->builder->build(
            [
                new PropertyCondition('purchase', 'product', Operator::In, false, ['Shoes', 'Hat']),
                new AggregateCondition('purchase', Aggregate::TotalAmount, Operator::Gte, '500'),
            ],
            MatchMode::All,
            self::EVENTS,
            self::KEYS,
            7,
            11,
        );

        self::assertSame(1, substr_count($sql, 'UNION ALL'));
        self::assertStringContainsString('FORCE INDEX (idx_ep_type_key_str)', $sql);
        self::assertStringContainsString('value_str IN (?, ?)', $sql);
        self::assertStringContainsString(
            'FROM customer_event_stats FORCE INDEX (idx_stats_type_amount) WHERE event_type_id = ? AND total_amount >= CAST(? AS DECIMAL(20,6))',
            $sql,
        );
        self::assertStringContainsString('HAVING COUNT(*) = ?', $sql);
        self::assertSame([1, 11, 'Shoes', 'Hat', 1, '500', 2, 7, 11], $params);
    }

    public function testAnyHasNoHaving(): void
    {
        [$sql] = $this->builder->build(
            [
                new AggregateCondition('signup', Aggregate::Count, Operator::Gte, '1'),
                new AggregateCondition('purchase', Aggregate::Count, Operator::Gte, '3'),
            ],
            MatchMode::Any,
            self::EVENTS,
            self::KEYS,
            0,
            10,
        );

        self::assertStringNotContainsString('HAVING', $sql);
        self::assertStringContainsString('FORCE INDEX (idx_stats_type_count)', $sql);
    }

    public function testLikeWildcardsAreEscaped(): void
    {
        [$sql, $params] = $this->builder->build(
            [
                new PropertyCondition('purchase', 'product', Operator::Contains, false, ['50%_off!']),
                new PropertyCondition('purchase', 'product', Operator::StartsWith, false, ['Sho']),
            ],
            MatchMode::All,
            self::EVENTS,
            self::KEYS,
            0,
            10,
        );

        self::assertStringContainsString("value_str LIKE ? ESCAPE '!'", $sql);
        self::assertSame('%50!%!_off!!%', $params[2]);
        self::assertSame('Sho%', $params[5]);
    }

    public function testNeqMapsToSqlOperator(): void
    {
        [$sql] = $this->builder->build(
            [new PropertyCondition('purchase', 'amount', Operator::Neq, true, ['0'])],
            MatchMode::All,
            self::EVENTS,
            self::KEYS,
            0,
            10,
        );

        self::assertStringContainsString('value_num <> CAST(? AS DECIMAL(20,6))', $sql);
    }
}
