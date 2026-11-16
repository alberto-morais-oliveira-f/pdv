<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Support;

use Am2tec\Pdv\Contracts\SubscriptionFulfiller;
use Am2tec\Pdv\Models\Sale;

final class NullSubscriptionFulfiller implements SubscriptionFulfiller
{
    public function fulfill(Sale $sale): void {}
}
