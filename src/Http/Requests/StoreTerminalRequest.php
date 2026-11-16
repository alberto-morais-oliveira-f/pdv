<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTerminalRequest extends FormRequest
{
    /**
     * Authorization is delegated to the host's configured route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'settlement_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return __('pdv::terminals.validation');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('pdv::terminals.attributes');
    }
}
