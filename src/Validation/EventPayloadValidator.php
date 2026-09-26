<?php

declare(strict_types=1);

namespace App\Validation;

use App\Core\Exception\ValidationException;
use App\Model\IncomingEvent;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Validates and normalizes a POST /api/events body. Collects every error
 * (field path => message) instead of stopping at the first one.
 */
final class EventPayloadValidator
{
    /** Event names and property keys: lower-case identifiers, e.g. "purchase", "page_view", "utm.source". */
    private const NAME_PATTERN = '/^[a-z][a-z0-9_.\-]{0,63}$/';
    private const MAX_PROPERTIES = 50;
    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_NAME_LENGTH = 255;
    /** Tolerated client clock skew for timestamps in the future. */
    private const MAX_FUTURE_SKEW = '+5 minutes';
    /** ISO 8601 with a mandatory timezone: 2026-04-10T12:00:00Z, 2026-04-10T14:00:00.123+02:00 */
    private const TIMESTAMP_PATTERN = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})'
        . 'T(?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})(\.\d{1,6})?(Z|[+\-]\d{2}:\d{2})$/';

    /**
     * @param array<string, mixed> $data Decoded JSON body.
     * @param DateTimeImmutable|null $now Injected in tests; defaults to the current time.
     *
     * @throws ValidationException
     */
    public function validate(array $data, ?DateTimeImmutable $now = null): IncomingEvent
    {
        $errors = [];
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // customer
        $customer = $data['customer'] ?? null;
        $email = null;
        $name = null;
        if (!is_array($customer) || array_is_list($customer)) {
            $errors['customer'] = 'Required object with an "email" field.';
        } else {
            $email = $this->email($customer['email'] ?? null, $errors);
            $name = $this->name($customer['name'] ?? null, $errors);
        }

        // event
        $event = $data['event'] ?? null;
        if (!is_string($event) || trim($event) === '') {
            $errors['event'] = 'Required non-empty string.';
            $event = null;
        } else {
            $event = mb_strtolower(trim($event));
            if (!preg_match(self::NAME_PATTERN, $event)) {
                $errors['event'] = 'Must start with a letter and contain only a-z, 0-9, "_", "-", "." (max 64 chars).';
            }
        }

        // properties (optional)
        $properties = $data['properties'] ?? [];
        if (!is_array($properties) || ($properties !== [] && array_is_list($properties))) {
            $errors['properties'] = 'Must be an object.';
            $properties = [];
        } elseif (count($properties) > self::MAX_PROPERTIES) {
            $errors['properties'] = sprintf('At most %d properties.', self::MAX_PROPERTIES);
        } else {
            foreach (array_keys($properties) as $key) {
                if (!preg_match(self::NAME_PATTERN, (string) $key)) {
                    $errors["properties.$key"] = 'Invalid key: lower-case letter first, then a-z, 0-9, "_", "-", "." (max 64 chars).';
                }
            }
        }

        // timestamp (optional, defaults to now)
        $occurredAt = $this->timestamp($data['timestamp'] ?? null, $now, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new IncomingEvent($email, $name, $event, $properties, $occurredAt);
    }

    /** @param array<string, string> $errors */
    private function email(mixed $value, array &$errors): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            $errors['customer.email'] = 'Required string.';

            return null;
        }

        $email = mb_strtolower(trim($value));
        if (strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['customer.email'] = 'Invalid email address.';

            return null;
        }

        return $email;
    }

    /** @param array<string, string> $errors */
    private function name(mixed $value, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            $errors['customer.name'] = 'Must be a string or null.';

            return null;
        }

        $name = trim($value);
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['customer.name'] = sprintf('At most %d characters.', self::MAX_NAME_LENGTH);
        }

        return $name === '' ? null : $name;
    }

    /** @param array<string, string> $errors */
    private function timestamp(mixed $value, DateTimeImmutable $now, array &$errors): DateTimeImmutable
    {
        if ($value === null) {
            return $now;
        }

        if (!is_string($value) || !preg_match(self::TIMESTAMP_PATTERN, $value, $m)) {
            $errors['timestamp'] = 'Must be an ISO 8601 date-time with timezone, e.g. "2026-04-10T12:00:00Z".';

            return $now;
        }

        // DateTimeImmutable silently rolls impossible values over (02-30 -> 03-02, 25:00 -> next day).
        if (!checkdate((int) $m['month'], (int) $m['day'], (int) $m['year'])
            || (int) $m['hour'] > 23 || (int) $m['minute'] > 59 || (int) $m['second'] > 59) {
            $errors['timestamp'] = 'Invalid date.';

            return $now;
        }

        $date = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        if ($date > $now->modify(self::MAX_FUTURE_SKEW)) {
            $errors['timestamp'] = 'Cannot be in the future.';
        }

        return $date;
    }
}
