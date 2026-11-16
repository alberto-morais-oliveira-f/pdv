<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Events;

use Am2tec\Pdv\Data\SaleCompletedPayload;

/**
 * Dispatched after a sale is committed (`pdv.sale.completed`).
 */
final readonly class SaleCompleted
{
    public const NAME = 'pdv.sale.completed';

    public function __construct(public SaleCompletedPayload $payload) {}
}
