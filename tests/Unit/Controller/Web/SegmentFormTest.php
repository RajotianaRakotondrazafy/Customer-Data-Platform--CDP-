<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Web;

use App\Controller\Web\SegmentForm;
use PHPUnit\Framework\TestCase;

final class SegmentFormTest extends TestCase
{
    public function testMapsFormRowsToApiBodyWithTypedValues(): void
    {
        $body = SegmentForm::toApiBody([
            'match'      => 'any',
            'cursor'     => '42',
            'conditions' => [
                ['kind' => 'property', 'event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => '100'],
                ['kind' => 'property', 'event' => 'purchase', 'property' => 'price', 'operator' => '<=', 'value' => ' 9.99 '],
                ['kind' => 'property', 'event' => 'purchase', 'property' => 'gift', 'operator' => '=', 'value' => 'true'],
                ['kind' => 'property', 'event' => 'purchase', 'property' => 'product', 'operator' => 'in', 'value' => 'Shoes, Hat'],
                ['kind' => 'property', 'event' => 'purchase', 'property' => 'sku', 'operator' => '=', 'value' => '"123"'],
                ['kind' => 'aggregate', 'event' => 'purchase', 'aggregate' => 'count', 'operator' => '>=', 'value' => '3'],
                ['kind' => 'exists', 'event' => 'signup', 'property' => 'ignored'],
            ],
        ]);

        self::assertSame([
            'match'      => 'any',
            'conditions' => [
                ['event' => 'purchase', 'property' => 'amount', 'operator' => '>', 'value' => 100],
                ['event' => 'purchase', 'property' => 'price', 'operator' => '<=', 'value' => 9.99],
                ['event' => 'purchase', 'property' => 'gift', 'operator' => '=', 'value' => true],
                ['event' => 'purchase', 'property' => 'product', 'operator' => 'in', 'value' => ['Shoes', 'Hat']],
                ['event' => 'purchase', 'property' => 'sku', 'operator' => '=', 'value' => '123'],
                ['event' => 'purchase', 'aggregate' => 'count', 'operator' => '>=', 'value' => 3],
                ['event' => 'signup'],
            ],
            'cursor'     => 42,
        ], $body);
    }

    public function testDefaultsToOneExampleRow(): void
    {
        $rows = SegmentForm::rows([]);

        self::assertCount(1, $rows);
        self::assertSame('purchase', $rows[0]['event']);
    }

    public function testIgnoresMalformedInput(): void
    {
        $body = SegmentForm::toApiBody(['match' => ['x'], 'cursor' => 'abc', 'conditions' => ['oops', ['event' => ['nested']]]]);

        self::assertSame('all', $body['match']);
        self::assertArrayNotHasKey('cursor', $body);
        self::assertSame([['event' => '', 'property' => '', 'operator' => '>', 'value' => '']], $body['conditions']);
    }
}
