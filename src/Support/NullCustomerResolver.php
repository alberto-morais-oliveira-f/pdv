<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Support;

use Am2tec\Pdv\Contracts\CustomerResolver;

final class NullCustomerResolver implements CustomerResolver
{
    public function resolve(?string $type, ?string $id): ?array
    {
        return null;
    }
}
