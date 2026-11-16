<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by a host fulfiller to refuse the sale for a business reason
 * (e.g. insufficient stock). Results in HTTP 422 and a full rollback.
 */
final class FulfillmentRejected extends RuntimeException
{
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __('pdv::sales.errors.fulfillment_rejected'), $code, $previous);
    }
}
