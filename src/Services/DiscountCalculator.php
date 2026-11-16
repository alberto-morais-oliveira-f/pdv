<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Services;

use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Enums\DiscountType;
use Am2tec\Pdv\Models\Coupon;
use Illuminate\Validation\ValidationException;

class DiscountCalculator
{
    /**
     * @param  array{mode?: string, amount?: int, percent_bps?: int, code?: string}  $discountInput
     * @return array{amount: int, type: DiscountType, percent_bps: ?int, coupon_id: ?int, coupon_code: ?string}
     */
    public function resolve(int $gross, array $discountInput, ?Coupon $coupon): array
    {
        $gross = max(0, $gross);
        $mode = DiscountType::tryFrom((string) ($discountInput['mode'] ?? DiscountType::None->value));

        if ($mode === null) {
            $this->refuse('discount.mode', __('pdv::sales.errors.discount_invalid_mode'));
        }

        return match ($mode) {
            DiscountType::None => $this->snapshot(0, DiscountType::None),
            DiscountType::ManualFixed => $this->manualFixed($gross, $discountInput),
            DiscountType::ManualPercent => $this->manualPercent($gross, $discountInput),
            DiscountType::Coupon => $this->fromCoupon($gross, $coupon),
        };
    }

    /**
     * @param  array{amount?: int}  $discountInput
     * @return array{amount: int, type: DiscountType, percent_bps: ?int, coupon_id: ?int, coupon_code: ?string}
     */
    private function manualFixed(int $gross, array $discountInput): array
    {
        return $this->snapshot(
            $this->clamp((int) ($discountInput['amount'] ?? 0), $gross),
            DiscountType::ManualFixed,
        );
    }

    /**
     * @param  array{percent_bps?: int}  $discountInput
     * @return array{amount: int, type: DiscountType, percent_bps: ?int, coupon_id: ?int, coupon_code: ?string}
     */
    private function manualPercent(int $gross, array $discountInput): array
    {
        $bps = (int) ($discountInput['percent_bps'] ?? 0);

        return $this->snapshot($this->percentAmount($gross, $bps), DiscountType::ManualPercent, $bps);
    }

    /**
     * @return array{amount: int, type: DiscountType, percent_bps: ?int, coupon_id: ?int, coupon_code: ?string}
     */
    private function fromCoupon(int $gross, ?Coupon $coupon): array
    {
        if ($coupon === null) {
            $this->refuse('discount.code', __('pdv::sales.errors.discount_coupon_required'));
        }

        $bps = $coupon->type === CouponType::Percent ? $coupon->value : null;
        $amount = $coupon->type === CouponType::Percent
            ? $this->percentAmount($gross, $coupon->value)
            : $this->clamp($coupon->value, $gross);

        return $this->snapshot($amount, DiscountType::Coupon, $bps, $coupon->id, $coupon->code);
    }

    private function percentAmount(int $gross, int $bps): int
    {
        return $this->clamp(intdiv($gross * max(0, $bps), 10000), $gross);
    }

    private function clamp(int $amount, int $gross): int
    {
        return max(0, min($amount, $gross));
    }

    /**
     * @return array{amount: int, type: DiscountType, percent_bps: ?int, coupon_id: ?int, coupon_code: ?string}
     */
    private function snapshot(
        int $amount,
        DiscountType $type,
        ?int $percentBps = null,
        ?int $couponId = null,
        ?string $couponCode = null,
    ): array {
        return [
            'amount' => $amount,
            'type' => $type,
            'percent_bps' => $percentBps,
            'coupon_id' => $couponId,
            'coupon_code' => $couponCode,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function refuse(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
