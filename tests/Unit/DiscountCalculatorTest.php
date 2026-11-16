<?php

declare(strict_types=1);

use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Enums\DiscountType;
use Am2tec\Pdv\Models\Coupon;
use Am2tec\Pdv\Services\DiscountCalculator;
use Am2tec\Pdv\Tests\TestCase;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

function discountCalculator(): DiscountCalculator
{
    return new DiscountCalculator;
}

function discountCoupon(CouponType $type, int $value, int $id = 7, string $code = 'PROMO10'): Coupon
{
    $coupon = new Coupon([
        'code' => $code,
        'type' => $type,
        'value' => $value,
        'active' => true,
    ]);
    $coupon->id = $id;

    return $coupon;
}

it('resolves none as a zero discount', function (): void {
    $resolved = discountCalculator()->resolve(10000, ['mode' => 'none'], null);

    expect($resolved)->toBe([
        'amount' => 0,
        'type' => DiscountType::None,
        'percent_bps' => null,
        'coupon_id' => null,
        'coupon_code' => null,
    ]);
});

it('ignores amount, percent and coupon when mode is none (XOR)', function (): void {
    $resolved = discountCalculator()->resolve(10000, [
        'mode' => 'none',
        'amount' => 500,
        'percent_bps' => 1000,
        'code' => 'PROMO10',
    ], discountCoupon(CouponType::Percent, 1000));

    expect($resolved['amount'])->toBe(0)
        ->and($resolved['type'])->toBe(DiscountType::None)
        ->and($resolved['coupon_id'])->toBeNull();
});

it('resolves a manual fixed amount in cents', function (): void {
    $resolved = discountCalculator()->resolve(10000, [
        'mode' => 'manual_fixed',
        'amount' => 1500,
    ], null);

    expect($resolved)->toBe([
        'amount' => 1500,
        'type' => DiscountType::ManualFixed,
        'percent_bps' => null,
        'coupon_id' => null,
        'coupon_code' => null,
    ]);
});

it('clamps a manual fixed amount to the gross', function (): void {
    $resolved = discountCalculator()->resolve(1000, [
        'mode' => 'manual_fixed',
        'amount' => 5000,
    ], null);

    expect($resolved['amount'])->toBe(1000);
});

it('ignores a coupon when mode is manual_fixed (XOR)', function (): void {
    $resolved = discountCalculator()->resolve(10000, [
        'mode' => 'manual_fixed',
        'amount' => 200,
    ], discountCoupon(CouponType::Fixed, 9999));

    expect($resolved['amount'])->toBe(200)
        ->and($resolved['type'])->toBe(DiscountType::ManualFixed)
        ->and($resolved['coupon_id'])->toBeNull();
});

it('resolves a manual percent with floor(gross * bps / 10000)', function (): void {
    $resolved = discountCalculator()->resolve(9999, [
        'mode' => 'manual_percent',
        'percent_bps' => 1000,
    ], null);

    expect($resolved)->toBe([
        'amount' => 999,
        'type' => DiscountType::ManualPercent,
        'percent_bps' => 1000,
        'coupon_id' => null,
        'coupon_code' => null,
    ]);
});

it('clamps a manual percent result to the gross', function (): void {
    $resolved = discountCalculator()->resolve(5000, [
        'mode' => 'manual_percent',
        'percent_bps' => 20000,
    ], null);

    expect($resolved['amount'])->toBe(5000)
        ->and($resolved['percent_bps'])->toBe(20000);
});

it('resolves a percent coupon with the same floor math', function (): void {
    $coupon = discountCoupon(CouponType::Percent, 1000);

    $resolved = discountCalculator()->resolve(10000, ['mode' => 'coupon', 'code' => 'PROMO10'], $coupon);

    expect($resolved)->toBe([
        'amount' => 1000,
        'type' => DiscountType::Coupon,
        'percent_bps' => 1000,
        'coupon_id' => 7,
        'coupon_code' => 'PROMO10',
    ]);
});

it('resolves a fixed coupon clamped to the gross', function (): void {
    $coupon = discountCoupon(CouponType::Fixed, 15000, id: 3, code: 'FIX5');

    $resolved = discountCalculator()->resolve(8000, ['mode' => 'coupon'], $coupon);

    expect($resolved)->toBe([
        'amount' => 8000,
        'type' => DiscountType::Coupon,
        'percent_bps' => null,
        'coupon_id' => 3,
        'coupon_code' => 'FIX5',
    ]);
});

it('ignores manual fields when mode is coupon (XOR)', function (): void {
    $resolved = discountCalculator()->resolve(10000, [
        'mode' => 'coupon',
        'amount' => 1,
        'percent_bps' => 1,
    ], discountCoupon(CouponType::Fixed, 500, code: 'FIX5'));

    expect($resolved['amount'])->toBe(500)
        ->and($resolved['type'])->toBe(DiscountType::Coupon)
        ->and($resolved['coupon_code'])->toBe('FIX5');
});

it('rejects coupon mode without a coupon', function (): void {
    discountCalculator()->resolve(10000, ['mode' => 'coupon', 'code' => 'GONE'], null);
})->throws(ValidationException::class);
