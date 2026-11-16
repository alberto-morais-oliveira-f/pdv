<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Contracts;

use Am2tec\Pdv\Models\Sale;

/**
 * Host-implemented side effect run when a sale containing service items is paid.
 */
interface ServiceFulfiller
{
    public function fulfill(Sale $sale): void;
}
