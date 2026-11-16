<?php

declare(strict_types=1);

use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Models\Coupon;
use Am2tec\Pdv\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeCoupon(array $overrides = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'PROMO10',
        'label' => 'Dez por cento',
        'type' => CouponType::Percent,
        'value' => 1000,
        'active' => true,
    ], $overrides));
}

it('can create a coupon', function (): void {
    $response = $this->postJson('/pdv/api/coupons', [
        'code' => 'promo10',
        'label' => 'Dez por cento',
        'type' => 'percent',
        'value' => 1000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'PROMO10')
        ->assertJsonPath('data.label', 'Dez por cento')
        ->assertJsonPath('data.type', 'percent')
        ->assertJsonPath('data.value', 1000)
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.uses_count', 0);

    $this->assertDatabaseHas('pdv_coupons', [
        'code' => 'PROMO10',
        'type' => 'percent',
        'value' => 1000,
        'active' => true,
    ]);
});

it('lists coupons', function (): void {
    makeCoupon(['code' => 'A']);
    makeCoupon(['code' => 'B', 'type' => CouponType::Fixed, 'value' => 500]);

    $this->getJson('/pdv/api/coupons')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'A')
        ->assertJsonPath('data.1.code', 'B');
});

it('returns an empty list when there are no coupons', function (): void {
    $this->getJson('/pdv/api/coupons')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('updates a coupon', function (): void {
    $coupon = makeCoupon();

    $this->putJson("/pdv/api/coupons/{$coupon->id}", [
        'code' => 'verao20',
        'type' => 'fixed',
        'value' => 2000,
        'active' => false,
        'label' => 'Verão',
        'max_uses' => 50,
    ])->assertOk()
        ->assertJsonPath('data.code', 'VERAO20')
        ->assertJsonPath('data.type', 'fixed')
        ->assertJsonPath('data.value', 2000)
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('data.label', 'Verão')
        ->assertJsonPath('data.max_uses', 50);

    $this->assertDatabaseHas('pdv_coupons', [
        'id' => $coupon->id,
        'code' => 'VERAO20',
        'active' => false,
    ]);
});

it('returns 404 when updating a missing coupon', function (): void {
    $this->putJson('/pdv/api/coupons/999', [
        'code' => 'X',
        'type' => 'percent',
        'value' => 1000,
    ])->assertNotFound();
});

it('deletes a coupon', function (): void {
    $coupon = makeCoupon();

    $this->deleteJson("/pdv/api/coupons/{$coupon->id}")->assertNoContent();

    $this->assertDatabaseMissing('pdv_coupons', ['id' => $coupon->id]);
    $this->getJson('/pdv/api/coupons')->assertJsonCount(0, 'data');
});

it('rejects a duplicate code', function (): void {
    makeCoupon(['code' => 'PROMO10']);

    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/coupons', [
        'code' => 'promo10',
        'type' => 'percent',
        'value' => 500,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('rejects invalid coupon fields with pt-BR messages', function (): void {
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/coupons', [
        'type' => 'combo',
        'value' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'type', 'value'])
        ->assertJsonPath('errors.code.0', 'O código do cupom é obrigatório.');
});

it('validates an active coupon and returns a discount preview', function (): void {
    makeCoupon();

    $this->getJson('/pdv/api/coupons/validate?code=promo10&gross=10000')
        ->assertOk()
        ->assertExactJson([
            'code' => 'PROMO10',
            'type' => 'percent',
            'value' => 1000,
            'discount_preview' => 1000,
        ]);
});

it('returns a null discount preview when gross is omitted', function (): void {
    makeCoupon(['code' => 'FIX5', 'type' => CouponType::Fixed, 'value' => 500]);

    $this->getJson('/pdv/api/coupons/validate?code=FIX5')
        ->assertOk()
        ->assertJsonPath('code', 'FIX5')
        ->assertJsonPath('type', 'fixed')
        ->assertJsonPath('value', 500)
        ->assertJsonPath('discount_preview', null);
});

it('rejects an inactive coupon on validate', function (): void {
    makeCoupon(['active' => false]);

    $this->app->setLocale('pt_BR');

    $this->getJson('/pdv/api/coupons/validate?code=PROMO10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code'])
        ->assertJsonPath('errors.code.0', 'Este cupom está inativo.');
});

it('rejects an expired coupon on validate', function (): void {
    makeCoupon(['ends_at' => now()->subDay()]);

    $this->app->setLocale('pt_BR');

    $this->getJson('/pdv/api/coupons/validate?code=PROMO10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code'])
        ->assertJsonPath('errors.code.0', 'Este cupom está expirado.');
});

it('rejects a coupon that has not started', function (): void {
    makeCoupon(['starts_at' => now()->addDay()]);

    $this->app->setLocale('pt_BR');

    $this->getJson('/pdv/api/coupons/validate?code=PROMO10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code'])
        ->assertJsonPath('errors.code.0', 'Este cupom ainda não está válido.');
});

it('rejects a coupon that reached max uses', function (): void {
    $coupon = makeCoupon(['max_uses' => 2]);
    $coupon->uses_count = 2;
    $coupon->save();

    $this->app->setLocale('pt_BR');

    $this->getJson('/pdv/api/coupons/validate?code=PROMO10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code'])
        ->assertJsonPath('errors.code.0', 'Este cupom atingiu o limite de usos.');
});

it('rejects an unknown coupon code', function (): void {
    $this->app->setLocale('pt_BR');

    $this->getJson('/pdv/api/coupons/validate?code=NOPE')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code'])
        ->assertJsonPath('errors.code.0', 'Cupom não encontrado.');
});

it('looks up coupon codes case-insensitively', function (): void {
    makeCoupon(['code' => 'promo10']);

    expect(app(CouponService::class)->findValidByCode('Promo10')->code)->toBe('PROMO10');
});

it('throws ValidationException from findValidByCode when the coupon is invalid', function (): void {
    makeCoupon(['active' => false]);

    app(CouponService::class)->findValidByCode('PROMO10');
})->throws(ValidationException::class);

it('registers coupon routes with the configured prefix and middleware', function (): void {
    $index = app('router')->getRoutes()->getByName('pdv.api.coupons.index');
    $validate = app('router')->getRoutes()->getByName('pdv.api.coupons.validate');

    expect($index)->not->toBeNull()
        ->and($index->uri())->toBe('pdv/api/coupons')
        ->and($index->gatherMiddleware())->toContain('api')
        ->and($validate)->not->toBeNull()
        ->and($validate->uri())->toBe('pdv/api/coupons/validate')
        ->and($validate->methods())->toContain('GET');
});
