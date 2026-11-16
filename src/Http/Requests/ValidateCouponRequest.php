<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
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
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => strtoupper($code)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'gross' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function gross(): ?int
    {
        $gross = $this->validated('gross');

        return $gross === null ? null : (int) $gross;
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
