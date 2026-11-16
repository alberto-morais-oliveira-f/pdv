<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Am2tec\Pdv\Enums\DiscountType;
use Am2tec\Pdv\Enums\PaymentMethod;
use Am2tec\Pdv\Enums\SaleItemType;
use Am2tec\Pdv\Services\CouponService;
use Am2tec\Pdv\Services\DiscountCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * Coupon codes are trimmed and uppercased so lookup matches stored rows.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];
        $items = $this->input('items');

        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (is_array($item) && isset($item['source_id']) && is_int($item['source_id'])) {
                    $items[$index]['source_id'] = (string) $item['source_id'];
                }
            }

            $payload['items'] = $items;
        }

        $discount = $this->input('discount');

        if (is_array($discount) && isset($discount['code']) && is_string($discount['code'])) {
            $discount['code'] = strtoupper(trim($discount['code']));
            $payload['discount'] = $discount;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $zeroPayable = $this->payableIsZero();

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
            'payments' => [Rule::when($zeroPayable, ['present'], ['required', 'min:1']), 'array', 'max:20'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', Rule::when($zeroPayable, ['min:0'], ['min:1']), 'max:100000000000'],
            'payments.*.terminal_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'array'],
            'discount.mode' => [Rule::requiredIf(fn (): bool => $this->exists('discount')), Rule::in(['none', 'manual_fixed', 'manual_percent', 'coupon'])],
            'discount.amount' => ['nullable', 'required_if:discount.mode,manual_fixed', 'integer', 'min:1'],
            'discount.percent_bps' => ['nullable', 'required_if:discount.mode,manual_percent', 'integer', 'min:1', 'max:10000'],
            'discount.code' => ['nullable', 'required_if:discount.mode,coupon', 'string', 'max:64'],
        ];
    }

    /**
     * Empty payments or amount 0 are allowed only when discount (or free items) zeros the payable.
     */
    private function payableIsZero(): bool
    {
        if (! $this->paymentsLookZero()) {
            return false;
        }

        $items = $this->input('items');

        if (! is_array($items) || $items === []) {
            return false;
        }

        $gross = 0;

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['qty'], $item['unit_amount'])) {
                return false;
            }

            if (! is_numeric($item['qty']) || ! is_numeric($item['unit_amount'])) {
                return false;
            }

            $qty = (int) $item['qty'];
            $unit = (int) $item['unit_amount'];

            if ($qty < 1 || $unit < 0) {
                return false;
            }

            $gross += $qty * $unit;
        }

        $discountInput = $this->input('discount');

        if (! is_array($discountInput)) {
            $discountInput = ['mode' => DiscountType::None->value];
        }

        try {
            $coupon = null;
            $mode = (string) ($discountInput['mode'] ?? DiscountType::None->value);

            if ($mode === DiscountType::Coupon->value) {
                $code = $discountInput['code'] ?? '';

                if (! is_string($code) || $code === '') {
                    return false;
                }

                $coupon = $this->container->make(CouponService::class)->findValidByCode($code);
            }

            $resolved = $this->container->make(DiscountCalculator::class)->resolve($gross, $discountInput, $coupon);

            return ($gross - $resolved['amount']) === 0;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * True when the payload would fail the usual min:1 payment rules without a zero payable.
     */
    private function paymentsLookZero(): bool
    {
        $payments = $this->input('payments');

        if (! is_array($payments) || $payments === []) {
            return true;
        }

        foreach ($payments as $payment) {
            if (! is_array($payment) || ! array_key_exists('amount', $payment) || ! is_numeric($payment['amount'])) {
                continue;
            }

            if ((int) $payment['amount'] < 1) {
                return true;
            }
        }

        return false;
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
