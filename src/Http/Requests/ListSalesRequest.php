<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ListSalesRequest extends FormRequest
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
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'customer_type' => ['nullable', 'string', 'max:255', 'required_with:customer_id'],
            'customer_id' => ['nullable', 'string', 'max:255', 'required_with:customer_type'],
        ];
    }

    /**
     * Requested day in the app timezone; defaults to today.
     */
    public function day(): Carbon
    {
        $timezone = (string) config('app.timezone');
        $date = $this->validated('date');

        return is_string($date)
            ? Carbon::createFromFormat('!Y-m-d', $date, $timezone)
            : Carbon::now($timezone)->startOfDay();
    }

    public function perPage(): int
    {
        $validated = $this->validated();

        if (isset($validated['limit'])) {
            return (int) $validated['limit'];
        }

        return (int) ($validated['per_page'] ?? 50);
    }

    public function hasCustomer(): bool
    {
        return $this->customerType() !== null && $this->customerId() !== null;
    }

    public function customerType(): ?string
    {
        $type = $this->validated('customer_type');

        return is_string($type) && $type !== '' ? $type : null;
    }

    public function customerId(): ?string
    {
        $id = $this->validated('customer_id');

        return is_string($id) && $id !== '' ? $id : null;
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
