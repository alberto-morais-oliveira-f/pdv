<?php

declare(strict_types=1);

use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Models\Coupon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a coupon', function (): void {
    $coupon = Coupon::query()->create([
        'code' => 'PROMO10',
        'type' => CouponType::Percent,
        'value' => 1000,
        'active' => true,
    ]);
    expect($coupon->fresh()->code)->toBe('PROMO10');
});

it('trims and uppercases coupon codes on write', function (): void {
    $coupon = Coupon::query()->create([
        'code' => '  promo10  ',
        'type' => CouponType::Percent,
        'value' => 1000,
        'active' => true,
    ]);

    expect($coupon->fresh()->code)->toBe('PROMO10');
});

it('uppercases coupon codes so uniqueness is case-insensitive', function (): void {
    Coupon::query()->create([
        'code' => 'promo10',
        'type' => CouponType::Percent,
        'value' => 1000,
        'active' => true,
    ]);

    expect(Coupon::query()->where('code', 'PROMO10')->value('code'))->toBe('PROMO10');

    Coupon::query()->create([
        'code' => 'PROMO10',
        'type' => CouponType::Percent,
        'value' => 1000,
        'active' => true,
    ]);
})->throws(QueryException::class);
