<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Tests\Support;

use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Contracts\ServiceFulfiller;
use Am2tec\Pdv\Contracts\SubscriptionFulfiller;
use Am2tec\Pdv\Models\Sale;
use RuntimeException;

/**
 * Test double that records the order in which fulfillers are called.
 */
final class RecordingFulfiller implements ProductFulfiller, ServiceFulfiller, SubscriptionFulfiller
{
    public function __construct(private readonly string $kind, private readonly ?CallLog $log = null, private readonly bool $fails = false) {}

    public function fulfill(Sale $sale): void
    {
        $this->log?->record($this->kind);

        if ($this->fails) {
            throw new RuntimeException('boom');
        }
    }
}
