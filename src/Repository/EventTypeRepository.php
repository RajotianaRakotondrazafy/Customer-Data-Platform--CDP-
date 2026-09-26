<?php

declare(strict_types=1);

namespace App\Repository;

final class EventTypeRepository extends DictionaryRepository
{
    protected function table(): string
    {
        return 'event_types';
    }
}
