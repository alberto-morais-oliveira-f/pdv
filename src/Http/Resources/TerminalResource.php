<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Resources;

use Am2tec\Pdv\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Terminal
 */
class TerminalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'settlement_days' => $this->settlement_days,
            'fee_percent' => (float) $this->fee_percent,
            'fee_fixed' => $this->fee_fixed,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
