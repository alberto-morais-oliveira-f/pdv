<?php

declare(strict_types=1);

use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Models\Coupon;
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
