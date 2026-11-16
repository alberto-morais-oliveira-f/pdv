<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Resources;

use Am2tec\Pdv\Data\SaleCompletedPayload;
use Am2tec\Pdv\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = SaleCompletedPayload::fromSale($this->resource)->toArray();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'occurred_at' => $payload['occurred_at'],
            'operator_id' => $payload['operator_id'],
            'customer' => $payload['customer'],
            'items' => $payload['items'],
            'payments' => $payload['payments'],
            'totals' => $payload['totals'],
        ];
    }
}
