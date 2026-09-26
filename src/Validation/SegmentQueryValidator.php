<?php

declare(strict_types=1);

namespace App\Validation;

use App\Core\Exception\ValidationException;
use App\Service\PropertyValue;
use App\Service\Segmentation\Aggregate;
use App\Service\Segmentation\AggregateCondition;
use App\Service\Segmentation\Condition;
use App\Service\Segmentation\MatchMode;
use App\Service\Segmentation\Operator;
use App\Service\Segmentation\PropertyCondition;
use App\Service\Segmentation\SegmentQuery;

/**
 * Validates a POST /api/segments/query body. Three condition shapes:
 *
 *   {"event": "purchase", "property": "amount", "operator": ">", "value": 100}   property condition
 *   {"event": "purchase", "aggregate": "count", "operator": ">=", "value": 3}    aggregate condition
 *   {"event": "purchase"}                                                        did the event at least once
 */
final class SegmentQueryValidator
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9_.\-]{0,63}$/';
    private const MAX_CONDITIONS = 20;
    private const MAX_IN_VALUES = 100;
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 1000;

    /**
     * @param array<string, mixed> $data Decoded JSON body.
     * @throws ValidationException
     */
    public function validate(array $data): SegmentQuery
    {
        $errors = [];

        $raw = $data['conditions'] ?? null;
        $conditions = [];
        if (!is_array($raw) || !array_is_list($raw) || $raw === []) {
            $errors['conditions'] = 'Required non-empty array.';
        } elseif (count($raw) > self::MAX_CONDITIONS) {
            $errors['conditions'] = sprintf('At most %d conditions.', self::MAX_CONDITIONS);
        } else {
            foreach ($raw as $i => $condition) {
                $parsed = $this->condition($condition, "conditions.$i", $errors);
                if ($parsed !== null) {
                    $conditions[] = $parsed;
                }
            }
        }

        $match = MatchMode::tryFrom(is_string($data['match'] ?? null) ? $data['match'] : '');
        if (!array_key_exists('match', $data)) {
            $match = MatchMode::All;
        } elseif ($match === null) {
            $errors['match'] = 'Must be "all" or "any".';
        }

        $limit = $this->positiveInt($data, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT, $errors);
        $cursor = $this->positiveInt($data, 'cursor', 0, 0, PHP_INT_MAX, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new SegmentQuery($conditions, $match, $limit, $cursor);
    }

    /** @param array<string, string> $errors */
    private function condition(mixed $c, string $path, array &$errors): ?Condition
    {
        if (!is_array($c) || array_is_list($c)) {
            $errors[$path] = 'Must be an object.';

            return null;
        }

        $event = $this->name($c['event'] ?? null, "$path.event", $errors);
        $hasProperty = array_key_exists('property', $c);
        $hasAggregate = array_key_exists('aggregate', $c);

        if ($hasProperty && $hasAggregate) {
            $errors[$path] = 'Use either "property" or "aggregate", not both.';

            return null;
        }

        // {"event": "purchase"} alone: did the event at least once.
        if (!$hasProperty && !$hasAggregate) {
            if (array_key_exists('operator', $c) || array_key_exists('value', $c)) {
                $errors[$path] = '"operator" and "value" require a "property" or an "aggregate".';

                return null;
            }

            return $event === null ? null : new AggregateCondition($event, Aggregate::Count, Operator::Gte, '1');
        }

        $operator = is_string($c['operator'] ?? null) ? Operator::tryFrom($c['operator']) : null;
        if ($operator === null) {
            $errors["$path.operator"] = 'Required, one of: ' . implode(', ', Operator::values()) . '.';
        }

        if ($hasAggregate) {
            return $this->aggregateCondition($c, $event, $operator, $path, $errors);
        }

        return $this->propertyCondition($c, $event, $operator, $path, $errors);
    }

    /**
     * @param array<string, mixed>  $c
     * @param array<string, string> $errors
     */
    private function aggregateCondition(array $c, ?string $event, ?Operator $operator, string $path, array &$errors): ?Condition
    {
        $aggregate = is_string($c['aggregate']) ? Aggregate::tryFrom($c['aggregate']) : null;
        if ($aggregate === null) {
            $errors["$path.aggregate"] = 'Must be "count" or "total_amount".';
        }
        if ($operator !== null && ($operator === Operator::In || $operator->isStringOnly())) {
            $errors["$path.operator"] = 'Aggregates support =, !=, >, >=, <, <= only.';
            $operator = null;
        }

        $value = $c['value'] ?? null;
        $normalized = is_int($value) || is_float($value) ? PropertyValue::normalize($value) : null;
        if ($normalized === null) {
            $errors["$path.value"] = 'Required number.';
        }

        if ($event === null || $aggregate === null || $operator === null || $normalized === null) {
            return null;
        }

        return new AggregateCondition($event, $aggregate, $operator, $normalized[1]);
    }

    /**
     * @param array<string, mixed>  $c
     * @param array<string, string> $errors
     */
    private function propertyCondition(array $c, ?string $event, ?Operator $operator, string $path, array &$errors): ?Condition
    {
        $property = $this->name($c['property'], "$path.property", $errors);

        if (!array_key_exists('value', $c)) {
            $errors["$path.value"] = 'Required.';

            return null;
        }

        $raw = $operator === Operator::In ? $c['value'] : [$c['value']];
        if ($operator === Operator::In
            && (!is_array($raw) || !array_is_list($raw) || $raw === [] || count($raw) > self::MAX_IN_VALUES)) {
            $errors["$path.value"] = sprintf('"in" requires a non-empty array of at most %d values.', self::MAX_IN_VALUES);

            return null;
        }

        $values = [];
        $types = [];
        foreach ($raw as $value) {
            $normalized = PropertyValue::normalize($value);
            if ($normalized === null) {
                $errors["$path.value"] = sprintf(
                    'Must be a number, a boolean or a string of at most %d characters.',
                    PropertyValue::MAX_STRING_LENGTH,
                );

                return null;
            }
            [$types[], $values[]] = $normalized;
        }

        if (count(array_unique($types)) > 1) {
            $errors["$path.value"] = '"in" values must be all numbers or all strings.';

            return null;
        }
        $numeric = $types[0];

        if ($operator !== null && $numeric && $operator->isStringOnly()) {
            $errors["$path.operator"] = "\"$operator->value\" requires a string value.";

            return null;
        }
        if ($operator !== null && $operator->isStringOnly() && $values[0] === '') {
            $errors["$path.value"] = 'Must not be empty.';

            return null;
        }

        if ($event === null || $property === null || $operator === null) {
            return null;
        }

        return new PropertyCondition($event, $property, $operator, $numeric, $values);
    }

    /** @param array<string, string> $errors */
    private function name(mixed $value, string $path, array &$errors): ?string
    {
        if (!is_string($value) || !preg_match(self::NAME_PATTERN, $name = mb_strtolower(trim($value)))) {
            $errors[$path] = 'Required identifier (a-z, 0-9, "_", "-", ".", max 64 chars).';

            return null;
        }

        return $name;
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function positiveInt(array $data, string $key, int $default, int $min, int $max, array &$errors): int
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }
        $value = $data[$key];
        if (!is_int($value) || $value < $min || $value > $max) {
            $errors[$key] = sprintf('Must be an integer between %d and %d.', $min, $max);

            return $default;
        }

        return $value;
    }
}
