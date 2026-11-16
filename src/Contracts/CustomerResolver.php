<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Contracts;

/**
 * Host-implemented resolution of the (optional) customer attached to a sale.
 */
interface CustomerResolver
{
    /**
     * @return array{type: class-string, id: int|string, label: string}|null
     */
    public function resolve(?string $type, ?string $id): ?array;
}
