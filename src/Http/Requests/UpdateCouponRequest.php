<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Requests;

use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Models\Coupon;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Coupon|int|string|null $coupon */
        $coupon = $this->route('coupon');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('pdv_coupons', 'code')->ignore($coupon)],
            'label' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(CouponType::class)],
            'value' => ['sometimes', 'required', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
