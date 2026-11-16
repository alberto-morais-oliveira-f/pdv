<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Am2tec\Pdv\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    /**
     * Authorization is delegated to the host's configured route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];
        $code = $this->input('code');

        if (is_string($code)) {
            $payload['code'] = strtoupper(trim($code));
        }

        $endsAt = $this->input('ends_at');

        if (is_string($endsAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt) === 1) {
            $payload['ends_at'] = Carbon::parse($endsAt)->endOfDay()->toDateTimeString();
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
        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('pdv_coupons', 'code')],
            'label' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'integer', 'min:1', Rule::when($this->valueIsPercent(), ['max:10000'])],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function valueIsPercent(): bool
    {
        return CouponType::tryFrom((string) $this->input('type')) === CouponType::Percent;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return __('pdv::coupons.validation');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('pdv::coupons.attributes');
    }
}
