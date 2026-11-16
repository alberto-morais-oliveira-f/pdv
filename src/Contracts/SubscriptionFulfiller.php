<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Contracts;

use Am2tec\Pdv\Models\Sale;

/**
 * Host-implemented side effect run when a sale containing subscription items is paid.
 */
interface SubscriptionFulfiller
{
    public function fulfill(Sale $sale): void;
}
