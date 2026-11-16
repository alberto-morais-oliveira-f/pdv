<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

class UpdateTerminalRequest extends StoreTerminalRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'settlement_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:3650'],
            'fee_percent' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
