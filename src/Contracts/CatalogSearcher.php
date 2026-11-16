<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Contracts;

use Am2tec\Pdv\Data\CatalogItemData;
use Illuminate\Support\Collection;

/**
 * Host-implemented catalog lookup (products, services, subscriptions).
 */
interface CatalogSearcher
{
    /**
     * @return Collection<int, CatalogItemData>
     */
    public function search(string $query, int $limit = 20): Collection;
}
