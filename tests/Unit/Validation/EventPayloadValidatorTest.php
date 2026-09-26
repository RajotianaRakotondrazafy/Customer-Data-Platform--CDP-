<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\Exception\ValidationException;
use App\Validation\EventPayloadValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventPayloadValidatorTest extends TestCase
{
    private EventPayloadValidator $validator;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->validator = new EventPayloadValidator();
        $this->now = new DateTimeImmutable('2026-04-10T12:30:00Z');
    }

    /** The example payload of the specification. */
    private static function valid(): array
    {
        return [
            'customer'   => ['email' => 'john@example.com', 'name' => 'John Doe'],
            'event'      => 'purchase',
            'properties' => ['amount' => 120, 'product' => 'Shoes'],
            'timestamp'  => '2026-04-10T12:00:00Z',
        ];
    }

    public function testAcceptsSpecificationExample(): void
    {
        $event = $this->validator->validate(self::valid(), $this->now);

        self::assertSame('john@example.com', $event->email);
        self::assertSame('John Doe', $event->name);
        self::assertSame('purchase', $event->event);
        self::assertSame(['amount' => 120, 'product' => 'Shoes'], $event->properties);
        self::assertSame('2026-04-10 12:00:00', $event->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testNormalizesEmailEventNameAndTimezone(): void
    {
        $payload = self::valid();
        $payload['customer'] = ['email' => '  John@Example.COM ', 'name' => '  '];
        $payload['event'] = ' Purchase ';
        $payload['timestamp'] = '2026-04-10T14:00:00.250+02:00';

        $event = $this->validator->validate($payload, $this->now);

        self::assertSame('john@example.com', $event->email);
        self::assertNull($event->name);
        self::assertSame('purchase', $event->event);
        self::assertSame('2026-04-10 12:00:00.250 UTC', $event->occurredAt->format('Y-m-d H:i:s.v T'));
    }

    public function testOptionalFieldsDefault(): void
    {
        $event = $this->validator->validate(
            ['customer' => ['email' => 'a@b.co'], 'event' => 'signup'],
            $this->now,
        );

        self::assertNull($event->name);
        self::assertSame([], $event->properties);
        self::assertEquals($this->now, $event->occurredAt);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        $v = self::valid();

        yield 'missing customer' => [array_diff_key($v, ['customer' => 1]), 'customer'];
        yield 'customer as list' => [['customer' => ['john@example.com']] + $v, 'customer'];
        yield 'missing email' => [['customer' => ['name' => 'John']] + $v, 'customer.email'];
        yield 'invalid email' => [['customer' => ['email' => 'not-an-email']] + $v, 'customer.email'];
        yield 'name not string' => [['customer' => ['email' => 'a@b.co', 'name' => 42]] + $v, 'customer.name'];
        yield 'missing event' => [array_diff_key($v, ['event' => 1]), 'event'];
        yield 'event with spaces' => [['event' => 'add to cart'] + $v, 'event'];
        yield 'event not string' => [['event' => 12] + $v, 'event'];
        yield 'properties as list' => [['properties' => [1, 2]] + $v, 'properties'];
        yield 'properties as string' => [['properties' => 'x'] + $v, 'properties'];
        yield 'invalid property key' => [['properties' => ['Bad Key' => 1]] + $v, 'properties.Bad Key'];
        yield 'timestamp without tz' => [['timestamp' => '2026-04-10T12:00:00'] + $v, 'timestamp'];
        yield 'timestamp not ISO' => [['timestamp' => '10/04/2026'] + $v, 'timestamp'];
        yield 'impossible date' => [['timestamp' => '2026-02-30T12:00:00Z'] + $v, 'timestamp'];
        yield 'impossible hour' => [['timestamp' => '2026-04-10T25:00:00Z'] + $v, 'timestamp'];
        yield 'future timestamp' => [['timestamp' => '2026-04-10T13:00:00Z'] + $v, 'timestamp'];
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayload(array $payload, string $field): void
    {
        try {
            $this->validator->validate($payload, $this->now);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey($field, $e->errors);
        }
    }

    public function testToleratesSmallClockSkew(): void
    {
        $event = $this->validator->validate(['timestamp' => '2026-04-10T12:33:00Z'] + self::valid(), $this->now);

        self::assertSame('12:33:00', $event->occurredAt->format('H:i:s'));
    }

    public function testCollectsAllErrorsAtOnce(): void
    {
        try {
            $this->validator->validate(['customer' => ['email' => 'bad'], 'event' => ''], $this->now);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['customer.email', 'event'], array_keys($e->errors));
        }
    }
}
