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
        $minMaxUses = $coupon instanceof Coupon ? max(1, $coupon->uses_count) : 1;

        return [
            'code' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('pdv_coupons', 'code')->ignore($coupon)],
            'label' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(CouponType::class)],
            'value' => ['sometimes', 'required', 'integer', 'min:1', Rule::when($this->valueIsPercent(), ['max:10000'])],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:'.$minMaxUses],
        ];
    }

    protected function valueIsPercent(): bool
    {
        $type = $this->input('type');

        if (is_string($type)) {
            return CouponType::tryFrom($type) === CouponType::Percent;
        }

        $coupon = $this->route('coupon');

        return $coupon instanceof Coupon && $coupon->type === CouponType::Percent;
    }
}
