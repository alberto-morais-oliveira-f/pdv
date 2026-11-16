<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleItemType;
use Am2tec\Pdv\Enums\SaleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::enum(SaleStatus::class)],
            'customer_type' => ['nullable', 'string', 'max:255', 'required_with:customer_id'],
            'customer_id' => ['nullable', 'string', 'max:255', 'required_with:customer_type'],
            'operator_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'payment_method' => ['sometimes', 'nullable', Rule::enum(PaymentMethod::class)],
            'terminal_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'item_type' => ['sometimes', 'nullable', Rule::enum(SaleItemType::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Inclusive start of the occurred_at window, or null when unbounded.
     *
     * Default is today unless a customer history query omits date/from/to.
     */
    public function occurredFrom(): ?Carbon
    {
        $timezone = (string) config('app.timezone');

        if ($this->filled('from')) {
            return Carbon::createFromFormat('!Y-m-d', (string) $this->validated('from'), $timezone);
        }

        if (! $this->filled('to') && $this->filled('date')) {
            return Carbon::createFromFormat('!Y-m-d', (string) $this->validated('date'), $timezone);
        }

        if ($this->filled('to') || $this->hasCustomer()) {
            return null;
        }

        return Carbon::now($timezone)->startOfDay();
    }

    /**
     * Exclusive end of the occurred_at window, or null when unbounded.
     */
    public function occurredToExclusive(): ?Carbon
    {
        $timezone = (string) config('app.timezone');

        if ($this->filled('to')) {
            return Carbon::createFromFormat('!Y-m-d', (string) $this->validated('to'), $timezone)->addDay();
        }

        if ($this->filled('from')) {
            return null;
        }

        if ($this->filled('date')) {
            return Carbon::createFromFormat('!Y-m-d', (string) $this->validated('date'), $timezone)->addDay();
        }

        if ($this->hasCustomer()) {
            return null;
        }

        return Carbon::now($timezone)->startOfDay()->addDay();
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
        return $this->optionalString('customer_type');
    }

    public function customerId(): ?string
    {
        return $this->optionalString('customer_id');
    }

    /**
     * @return array{
     *     from: Carbon|null,
     *     to: Carbon|null,
     *     status: SaleStatus|null,
     *     customer_type: string|null,
     *     customer_id: string|null,
     *     operator_id: int|null,
     *     payment_method: PaymentMethod|null,
     *     terminal_id: int|null,
     *     item_type: SaleItemType|null,
     *     q: string|null,
     *     category: string|null,
     *     per_page: int
     * }
     */
    public function criteria(): array
    {
        return [
            'from' => $this->occurredFrom(),
            'to' => $this->occurredToExclusive(),
            'status' => $this->enumFrom('status', SaleStatus::class),
            'customer_type' => $this->customerType(),
            'customer_id' => $this->customerId(),
            'operator_id' => $this->optionalInt('operator_id'),
            'payment_method' => $this->enumFrom('payment_method', PaymentMethod::class),
            'terminal_id' => $this->optionalInt('terminal_id'),
            'item_type' => $this->enumFrom('item_type', SaleItemType::class),
            'q' => $this->searchTerm(),
            'category' => $this->optionalString('category'),
            'per_page' => $this->perPage(),
        ];
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

    public function searchTerm(): ?string
    {
        $term = $this->optionalString('q');

        if ($term === null) {
            return null;
        }

        $trimmed = trim($term);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function enumFrom(string $key, string $enum): mixed
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $enum::from($value) : null;
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function optionalInt(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
