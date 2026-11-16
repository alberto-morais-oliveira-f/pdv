<?php

declare(strict_types=1);

use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Enums\CouponType;
use Am2tec\Pdv\Enums\DiscountType;
use Am2tec\Pdv\Events\SaleCancelled;
use Am2tec\Pdv\Events\SaleCompleted;
use Am2tec\Pdv\Models\Coupon;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Models\Terminal;
use Am2tec\Pdv\Services\CouponService;
use Am2tec\Pdv\Services\DiscountCalculator;
use Am2tec\Pdv\Tests\Support\RecordingFulfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function discountedSaleItem(int $unit = 10000): array
{
    return [
        'type' => 'product',
        'source_type' => 'stock_product',
        'source_id' => '10',
        'description' => 'Kimono A2',
        'qty' => 1,
        'unit_amount' => $unit,
        'metadata' => ['sku' => 'KIM-A2'],
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function discountedSaleCoupon(array $overrides = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'PROMO10',
        'label' => 'Dez por cento',
        'type' => CouponType::Percent,
        'value' => 1000,
        'active' => true,
    ], $overrides));
}

it('completes a sale with a 10 percent coupon matching payable not gross', function (): void {
    Event::fake([SaleCompleted::class]);
    $coupon = discountedSaleCoupon();

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'PROMO10'],
    ])->assertCreated()
        ->assertJsonPath('data.totals.gross', 10000)
        ->assertJsonPath('data.totals.discount', 1000)
        ->assertJsonPath('data.totals.fees', 0)
        ->assertJsonPath('data.totals.net', 9000)
        ->assertJsonPath('data.discount.type', 'coupon')
        ->assertJsonPath('data.discount.percent_bps', 1000)
        ->assertJsonPath('data.discount.coupon_id', $coupon->id)
        ->assertJsonPath('data.discount.coupon_code', 'PROMO10');

    $sale = Sale::query()->firstOrFail();
    expect($sale->gross_amount)->toBe(10000)
        ->and($sale->discount_amount)->toBe(1000)
        ->and($sale->discount_type)->toBe(DiscountType::Coupon)
        ->and($sale->discount_percent_bps)->toBe(1000)
        ->and($sale->coupon_id)->toBe($coupon->id)
        ->and($sale->coupon_code_snapshot)->toBe('PROMO10')
        ->and($sale->fees_amount)->toBe(0)
        ->and($sale->net_amount)->toBe(9000)
        ->and($sale->payments)->toHaveCount(1)
        ->and($sale->payments->first()->amount)->toBe(9000)
        ->and($coupon->fresh()->uses_count)->toBe(1);

    Event::assertDispatched(SaleCompleted::class, function (SaleCompleted $event) use ($sale, $coupon): bool {
        $payload = $event->payload->toArray();

        return $payload['sale_id'] === $sale->id
            && $payload['totals'] === ['gross' => 10000, 'discount' => 1000, 'fees' => 0, 'net' => 9000]
            && $payload['discount'] === [
                'type' => 'coupon',
                'percent_bps' => 1000,
                'coupon_id' => $coupon->id,
                'coupon_code' => 'PROMO10',
            ];
    });
});

it('rejects payments that match gross instead of payable after discount', function (): void {
    Event::fake([SaleCompleted::class]);
    discountedSaleCoupon();
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 10000]],
        'discount' => ['mode' => 'coupon', 'code' => 'PROMO10'],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.payments.0', 'A soma dos pagamentos deve ser igual ao total da venda.');

    expect(Sale::query()->count())->toBe(0)
        ->and(Coupon::query()->firstOrFail()->uses_count)->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});

it('applies a manual fixed discount driven by mode', function (): void {
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 7500]],
        'discount' => ['mode' => 'manual_fixed', 'amount' => 2500, 'code' => 'PROMO10', 'percent_bps' => 5000],
    ])->assertCreated()
        ->assertJsonPath('data.totals.discount', 2500)
        ->assertJsonPath('data.totals.net', 7500)
        ->assertJsonPath('data.discount.type', 'manual_fixed')
        ->assertJsonPath('data.discount.coupon_id', null)
        ->assertJsonPath('data.discount.coupon_code', null);

    $sale = Sale::query()->firstOrFail();
    expect($sale->discount_type)->toBe(DiscountType::ManualFixed)
        ->and($sale->discount_amount)->toBe(2500)
        ->and($sale->coupon_id)->toBeNull();
});

it('applies a manual percent discount driven by mode', function (): void {
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 7500]],
        'discount' => ['mode' => 'manual_percent', 'percent_bps' => 2500, 'amount' => 1, 'code' => 'PROMO10'],
    ])->assertCreated()
        ->assertJsonPath('data.totals.discount', 2500)
        ->assertJsonPath('data.totals.net', 7500)
        ->assertJsonPath('data.discount.type', 'manual_percent')
        ->assertJsonPath('data.discount.percent_bps', 2500);
});

it('computes card fees and net from payable not gross', function (): void {
    $terminal = Terminal::query()->create([
        'name' => 'Maquininha',
        'settlement_days' => 30,
        'fee_percent' => 2.99,
        'fee_fixed' => 10,
    ]);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'card', 'amount' => 9000, 'terminal_id' => $terminal->id]],
        'discount' => ['mode' => 'manual_percent', 'percent_bps' => 1000],
    ])->assertCreated()
        ->assertJsonPath('data.totals.gross', 10000)
        ->assertJsonPath('data.totals.discount', 1000)
        ->assertJsonPath('data.totals.fees', 279)
        ->assertJsonPath('data.totals.net', 8721);
});

it('requires discount.mode and the field that belongs to that mode', function (array $discount, string $field): void {
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 10000]],
        'discount' => $discount,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Sale::query()->count())->toBe(0);
})->with([
    'missing mode' => [[], 'discount.mode'],
    'manual_fixed without amount' => [['mode' => 'manual_fixed'], 'discount.amount'],
    'manual_fixed with null amount' => [['mode' => 'manual_fixed', 'amount' => null], 'discount.amount'],
    'manual_percent without percent' => [['mode' => 'manual_percent'], 'discount.percent_bps'],
    'coupon without code' => [['mode' => 'coupon'], 'discount.code'],
    'percent above 10000' => [['mode' => 'manual_percent', 'percent_bps' => 10001], 'discount.percent_bps'],
]);

it('accepts null unused discount fields from a full form', function (array $discount, int $paid, int $expectedDiscount): void {
    discountedSaleCoupon();

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => $paid]],
        'discount' => $discount,
    ])->assertCreated()
        ->assertJsonPath('data.totals.discount', $expectedDiscount)
        ->assertJsonPath('data.totals.net', $paid);
})->with([
    'coupon' => [['mode' => 'coupon', 'code' => 'PROMO10', 'amount' => null, 'percent_bps' => null], 9000, 1000],
    'none' => [['mode' => 'none', 'amount' => null, 'percent_bps' => null, 'code' => null], 10000, 0],
    'manual_fixed' => [['mode' => 'manual_fixed', 'amount' => 2500, 'percent_bps' => null, 'code' => null], 7500, 2500],
]);

it('completes a zero-payable sale without collecting money', function (array $discount, array $payments): void {
    Event::fake([SaleCompleted::class]);
    discountedSaleCoupon(['code' => 'FULL', 'type' => CouponType::Percent, 'value' => 10000]);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => $payments,
        'discount' => $discount,
    ])->assertCreated()
        ->assertJsonPath('data.totals.gross', 10000)
        ->assertJsonPath('data.totals.discount', 10000)
        ->assertJsonPath('data.totals.fees', 0)
        ->assertJsonPath('data.totals.net', 0);

    $sale = Sale::query()->firstOrFail();
    expect($sale->net_amount)->toBe(0)
        ->and($sale->payments->sum('amount'))->toBe(0)
        ->and($sale->payments->every(fn ($payment): bool => $payment->amount === 0))->toBeTrue();
})->with([
    'coupon empty payments' => [['mode' => 'coupon', 'code' => 'FULL'], []],
    'coupon amount 0' => [['mode' => 'coupon', 'code' => 'FULL'], [['method' => 'cash', 'amount' => 0]]],
    'manual 100 percent empty' => [['mode' => 'manual_percent', 'percent_bps' => 10000], []],
    'manual 100 percent amount 0' => [['mode' => 'manual_percent', 'percent_bps' => 10000], [['method' => 'cash', 'amount' => 0]]],
]);

it('still requires a payment when payable is not zero', function (): void {
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [],
        'discount' => ['mode' => 'manual_percent', 'percent_bps' => 1000],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['payments']);

    expect(Sale::query()->count())->toBe(0);
});

it('rejects an invalid coupon without creating a sale', function (): void {
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'GONE'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['discount.code']);

    expect(Sale::query()->count())->toBe(0);
});

it('rolls back coupon uses_count when fulfillment fails', function (): void {
    Event::fake([SaleCompleted::class]);
    $coupon = discountedSaleCoupon();
    $this->app->instance(ProductFulfiller::class, new RecordingFulfiller('product', null, true));

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'PROMO10'],
    ])->assertStatus(500);

    expect(Sale::query()->count())->toBe(0)
        ->and($coupon->fresh()->uses_count)->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});

it('does not consume a coupon that flipped inactive or out of window after lookup', function (string $column, mixed $value): void {
    Event::fake([SaleCompleted::class]);
    $coupon = discountedSaleCoupon();
    $calculator = app(DiscountCalculator::class);

    $this->app->instance(CouponService::class, new class($calculator, $column, $value) extends CouponService
    {
        public function __construct(
            DiscountCalculator $calculator,
            private readonly string $column,
            private readonly mixed $value,
        ) {
            parent::__construct($calculator);
        }

        public function findValidByCode(string $code): Coupon
        {
            $found = parent::findValidByCode($code);
            Coupon::query()->whereKey($found->id)->update([$this->column => $this->value]);

            return $found;
        }
    });

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'PROMO10'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['discount.code']);

    expect(Sale::query()->count())->toBe(0)
        ->and($coupon->fresh()->uses_count)->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
})->with([
    'inactive' => ['active', false],
    'not started' => ['starts_at', now()->addDay()],
    'expired' => ['ends_at', now()->subDay()],
]);

it('rejects a second use after the coupon reaches max_uses', function (): void {
    discountedSaleCoupon(['max_uses' => 1]);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'promo10'],
    ])->assertCreated();

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'PROMO10'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['discount.code']);

    expect(Sale::query()->count())->toBe(1)
        ->and(Coupon::query()->firstOrFail()->uses_count)->toBe(1);
});

it('cancels a discounted sale with the same totals shape', function (): void {
    Event::fake([SaleCancelled::class]);
    discountedSaleCoupon();

    $created = $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [discountedSaleItem(10000)],
        'payments' => [['method' => 'cash', 'amount' => 9000]],
        'discount' => ['mode' => 'coupon', 'code' => 'PROMO10'],
    ])->assertCreated();

    $saleId = $created->json('data.id');

    $this->deleteJson("/pdv/api/sales/{$saleId}")
        ->assertOk()
        ->assertJsonPath('data.totals.gross', 10000)
        ->assertJsonPath('data.totals.discount', 1000)
        ->assertJsonPath('data.totals.fees', 0)
        ->assertJsonPath('data.totals.net', 9000)
        ->assertJsonPath('data.discount.coupon_code', 'PROMO10');

    Event::assertDispatched(SaleCancelled::class, function (SaleCancelled $event) use ($saleId): bool {
        $payload = $event->payload->toArray();

        return $payload['sale_id'] === $saleId
            && $payload['totals'] === ['gross' => 10000, 'discount' => 1000, 'fees' => 0, 'net' => 9000]
            && $payload['discount']['coupon_code'] === 'PROMO10';
    });
});
