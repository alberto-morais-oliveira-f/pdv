<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Support;

use Am2tec\Pdv\Contracts\CatalogSearcher;
use Illuminate\Support\Collection;

final class NullCatalogSearcher implements CatalogSearcher
{
    public function search(string $query, int $limit = 20): Collection
    {
        return new Collection;
    }
}
