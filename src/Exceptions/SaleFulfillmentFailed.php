<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A host fulfiller threw while completing a sale; the whole sale was rolled back.
 */
final class SaleFulfillmentFailed extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self(__('pdv::sales.errors.fulfillment_failed'), 0, $previous);
    }
}
