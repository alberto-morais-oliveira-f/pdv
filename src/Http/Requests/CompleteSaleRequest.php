<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteSaleRequest extends FormRequest
{
    /**
     * Authorization is delegated to the host's configured route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Catalog ids may arrive as JSON numbers; they are stored as strings.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (is_array($item) && isset($item['source_id']) && is_int($item['source_id'])) {
                $items[$index]['source_id'] = (string) $item['source_id'];
            }
        }

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'operator_id' => ['nullable', 'integer', 'min:1'],
            'customer_type' => ['nullable', 'string', 'max:255', 'required_with:customer_id'],
            'customer_id' => ['nullable', 'string', 'max:255', 'required_with:customer_type'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.type' => ['required', Rule::enum(SaleItemType::class)],
            'items.*.source_type' => ['required', 'string', 'max:255'],
            'items.*.source_id' => ['required', 'string', 'max:255'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'items.*.metadata' => ['nullable', 'array'],
            'payments' => ['required', 'array', 'min:1', 'max:20'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'payments.*.terminal_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Authenticated user wins over client input so an operator cannot be spoofed.
     *
     * @return array<string, mixed>
     */
    public function saleData(): array
    {
        $data = $this->validated();
        $authId = $this->user()?->getAuthIdentifier();

        if ($authId !== null) {
            $data['operator_id'] = $authId;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return __('pdv::sales.validation');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('pdv::sales.attributes');
    }
}
