<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\Exception\ValidationException;
use App\Service\Segmentation\Aggregate;
use App\Service\Segmentation\AggregateCondition;
use App\Service\Segmentation\MatchMode;
use App\Service\Segmentation\Operator;
use App\Service\Segmentation\PropertyCondition;
use App\Validation\SegmentQueryValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SegmentQueryValidatorTest extends TestCase
{
    private SegmentQueryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SegmentQueryValidator();
    }

    public function testAcceptsSpecificationExampleWithDefaults(): void
    {
        $query = $this->validator->validate(['conditions' => [
            ['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100],
        ]]);

        self::assertSame(MatchMode::All, $query->match);
        self::assertSame(100, $query->limit);
        self::assertSame(0, $query->cursor);
        self::assertEquals(
            [new PropertyCondition('purchase', 'amount', Operator::Gt, true, ['100'])],
            $query->conditions,
        );
    }

    public function testParsesAllConditionShapes(): void
    {
        $query = $this->validator->validate([
            'match'      => 'any',
            'limit'      => 10,
            'cursor'     => 42,
            'conditions' => [
                ['event' => 'Purchase', 'property' => 'product', 'operator' => 'in', 'value' => ['Shoes', 'Hat']],
                ['event' => 'purchase', 'property' => 'gift', 'operator' => '=', 'value' => true],
                ['event' => 'purchase', 'property' => 'price', 'operator' => '<=', 'value' => 9.99],
                ['event' => 'purchase', 'aggregate' => 'count', 'operator' => '>=', 'value' => 3],
                ['event' => 'signup'],
            ],
        ]);

        self::assertSame(MatchMode::Any, $query->match);
        self::assertSame([10, 42], [$query->limit, $query->cursor]);
        self::assertEquals([
            new PropertyCondition('purchase', 'product', Operator::In, false, ['Shoes', 'Hat']),
            new PropertyCondition('purchase', 'gift', Operator::Eq, true, ['1']),
            new PropertyCondition('purchase', 'price', Operator::Lte, true, ['9.990000']),
            new AggregateCondition('purchase', Aggregate::Count, Operator::Gte, '3'),
            new AggregateCondition('signup', Aggregate::Count, Operator::Gte, '1'),
        ], $query->conditions);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidQueries(): iterable
    {
        $c = ['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100];

        yield 'no conditions' => [[], 'conditions'];
        yield 'empty conditions' => [['conditions' => []], 'conditions'];
        yield 'conditions object' => [['conditions' => ['a' => $c]], 'conditions'];
        yield 'too many conditions' => [['conditions' => array_fill(0, 21, $c)], 'conditions'];
        yield 'condition not object' => [['conditions' => ['x']], 'conditions.0'];
        yield 'missing event' => [['conditions' => [array_diff_key($c, ['event' => 1])]], 'conditions.0.event'];
        yield 'bad operator' => [['conditions' => [['operator' => 'LIKE'] + $c]], 'conditions.0.operator'];
        yield 'missing value' => [['conditions' => [array_diff_key($c, ['value' => 1])]], 'conditions.0.value'];
        yield 'value is object' => [['conditions' => [['value' => ['a' => 1]] + $c]], 'conditions.0.value'];
        yield 'in not array' => [['conditions' => [['operator' => 'in', 'value' => 1] + $c]], 'conditions.0.value'];
        yield 'in mixed types' => [['conditions' => [['operator' => 'in', 'value' => [1, 'a']] + $c]], 'conditions.0.value'];
        yield 'contains on number' => [['conditions' => [['operator' => 'contains'] + $c]], 'conditions.0.operator'];
        yield 'property and aggregate' => [['conditions' => [['aggregate' => 'count'] + $c]], 'conditions.0'];
        yield 'operator without property' => [['conditions' => [['event' => 'purchase', 'operator' => '>', 'value' => 1]]], 'conditions.0'];
        yield 'bad aggregate' => [['conditions' => [['event' => 'purchase', 'aggregate' => 'avg', 'operator' => '>', 'value' => 1]]], 'conditions.0.aggregate'];
        yield 'aggregate string value' => [['conditions' => [['event' => 'purchase', 'aggregate' => 'count', 'operator' => '>', 'value' => '3']]], 'conditions.0.value'];
        yield 'aggregate with in' => [['conditions' => [['event' => 'purchase', 'aggregate' => 'count', 'operator' => 'in', 'value' => 3]]], 'conditions.0.operator'];
        yield 'bad match' => [['conditions' => [$c], 'match' => 'some'], 'match'];
        yield 'limit too high' => [['conditions' => [$c], 'limit' => 5000], 'limit'];
        yield 'negative cursor' => [['conditions' => [$c], 'cursor' => -1], 'cursor'];
    }

    #[DataProvider('invalidQueries')]
    public function testRejectsInvalidQuery(array $data, string $field): void
    {
        try {
            $this->validator->validate($data);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey($field, $e->errors);
        }
    }
}
