<?php

declare(strict_types=1);

namespace App\Repository;

final class PropertyKeyRepository extends DictionaryRepository
{
    protected function table(): string
    {
        return 'property_keys';
    }
}
