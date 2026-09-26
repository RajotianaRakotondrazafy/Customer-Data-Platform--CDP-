<?php

declare(strict_types=1);

namespace App\Service\Segmentation;

/**
 * "The customer has at least one <event> whose <property> <operator> <value>".
 * e.g. purchase.amount > 100, purchase.product in ["Shoes", "Hat"].
 */
final readonly class PropertyCondition implements Condition
{
    /**
     * @param bool         $numeric Compare against value_num (true) or value_str (false).
     * @param list<string> $values  Normalized values (numeric strings when $numeric);
     *                              several only for Operator::In.
     */
    public function __construct(
        public string $event,
        public string $property,
        public Operator $operator,
        public bool $numeric,
        public array $values,
    ) {
    }

    public function event(): string
    {
        return $this->event;
    }
}
