<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Support;

use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Models\Sale;

final class NullProductFulfiller implements ProductFulfiller
{
    public function fulfill(Sale $sale): void {}
}
