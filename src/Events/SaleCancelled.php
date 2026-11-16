<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Events;

use Am2tec\Pdv\Data\SaleCompletedPayload;

/**
 * Dispatched after a sale cancellation is committed (`pdv.sale.cancelled`).
 * Carries the same payload as `SaleCompleted` so hosts can reverse what they fulfilled.
 */
final readonly class SaleCancelled
{
    public const NAME = 'pdv.sale.cancelled';

    public function __construct(public SaleCompletedPayload $payload) {}
}
