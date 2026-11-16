<?php

declare(strict_types=1);

use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Contracts\ServiceFulfiller;
use Am2tec\Pdv\Contracts\SubscriptionFulfiller;
use Am2tec\Pdv\Events\SaleCompleted;
use Am2tec\Pdv\Exceptions\FulfillmentRejected;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Models\SalePayment;
use Am2tec\Pdv\Models\Terminal;
use Am2tec\Pdv\Services\SaleCompletionService;
use Am2tec\Pdv\Tests\Support\CallLog;
use Am2tec\Pdv\Tests\Support\RecordingFulfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function productItem(int $unit = 1000, int $qty = 1): array
{
    return [
        'type' => 'product',
        'source_type' => 'stock_product',
        'source_id' => '10',
        'description' => 'Kimono A2',
        'qty' => $qty,
        'unit_amount' => $unit,
        'metadata' => ['sku' => 'KIM-A2'],
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function cardTerminal(array $overrides = []): Terminal
{
    return Terminal::query()->create(array_merge([
        'name' => 'Maquininha',
        'settlement_days' => 30,
        'fee_percent' => 2.99,
        'fee_fixed' => 10,
    ], $overrides));
}

it('completes a cash-only sale with one product', function (): void {
    Event::fake([SaleCompleted::class]);

    $response = $this->postJson('/pdv/api/sales', [
        'operator_id' => 7,
        'items' => [productItem(1000, 2)],
        'payments' => [['method' => 'cash', 'amount' => 2000]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.totals.gross', 2000)
        ->assertJsonPath('data.totals.fees', 0)
        ->assertJsonPath('data.totals.net', 2000)
        ->assertJsonPath('data.items.0.total_amount', 2000);

    $sale = Sale::query()->firstOrFail();
    expect($sale->gross_amount)->toBe(2000)
        ->and($sale->fees_amount)->toBe(0)
        ->and($sale->net_amount)->toBe(2000)
        ->and($sale->operator_id)->toBe(7)
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->payments)->toHaveCount(1);

    Event::assertDispatched(SaleCompleted::class, function (SaleCompleted $event) use ($sale): bool {
        $payload = $event->payload->toArray();

        return $payload['sale_id'] === $sale->id
            && $payload['operator_id'] === 7
            && $payload['customer'] === null
            && $payload['totals'] === ['gross' => 2000, 'fees' => 0, 'net' => 2000]
            && $payload['items'][0] === [
                'type' => 'product',
                'source_type' => 'stock_product',
                'source_id' => '10',
                'description' => 'Kimono A2',
                'qty' => 2,
                'unit_amount' => 1000,
                'total_amount' => 2000,
                'metadata' => ['sku' => 'KIM-A2'],
            ]
            && $payload['payments'][0] === [
                'method' => 'cash',
                'amount' => 2000,
                'terminal_id' => null,
                'fee_percent' => null,
                'fee_amount' => null,
                'settlement_days' => null,
                'expected_settlement_at' => null,
            ]
            && is_string($payload['occurred_at']);
    });
});

it('splits a sale between pix and card and computes fees and settlement', function (): void {
    Event::fake([SaleCompleted::class]);
    Carbon::setTestNow(Carbon::parse('2026-11-15 15:30:00', config('app.timezone')));
    $terminal = cardTerminal();

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem(10000)],
        'payments' => [
            ['method' => 'pix', 'amount' => 4000],
            ['method' => 'card', 'amount' => 6000, 'terminal_id' => $terminal->id],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.totals.gross', 10000)
        ->assertJsonPath('data.totals.fees', 189)
        ->assertJsonPath('data.totals.net', 9811);

    // 6000 * 2.99% = 179.4 -> 179, + 10 fixed = 189
    $card = SalePayment::query()->where('method', 'card')->firstOrFail();
    expect($card->fee_amount)->toBe(189)
        ->and($card->fee_percent)->toBe('2.99')
        ->and($card->fee_fixed_snapshot)->toBe(10)
        ->and($card->settlement_days)->toBe(30)
        ->and($card->terminal_id)->toBe($terminal->id)
        ->and($card->expected_settlement_at->toDateTimeString())->toBe('2026-12-15 00:00:00');

    $pix = SalePayment::query()->where('method', 'pix')->firstOrFail();
    expect($pix->fee_amount)->toBe(0)->and($pix->terminal_id)->toBeNull();

    Event::assertDispatched(SaleCompleted::class, function (SaleCompleted $event) use ($terminal): bool {
        $card = $event->payload->toArray()['payments'][1];

        return $card['terminal_id'] === $terminal->id
            && $card['fee_amount'] === 189
            && $card['settlement_days'] === 30
            && $card['expected_settlement_at'] !== null;
    });

    Carbon::setTestNow();
});

it('rejects a card payment without a terminal', function (): void {
    Event::fake([SaleCompleted::class]);
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem()],
        'payments' => [['method' => 'card', 'amount' => 1000]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['payments.0.terminal_id' => 'O pagamento com cartão exige uma maquineta.']);

    expect(Sale::query()->count())->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});

it('rejects a card payment with an inactive or deleted terminal', function (string $state): void {
    $terminal = cardTerminal();
    $state === 'inactive' ? $terminal->update(['active' => false]) : $terminal->delete();

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem()],
        'payments' => [['method' => 'card', 'amount' => 1000, 'terminal_id' => $terminal->id]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['payments.0.terminal_id']);

    expect(Sale::query()->count())->toBe(0);
})->with(['inactive', 'deleted']);

it('rejects payments that do not match the gross amount', function (int $paid): void {
    Event::fake([SaleCompleted::class]);
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem(1000)],
        'payments' => [['method' => 'cash', 'amount' => $paid]],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.payments.0', 'A soma dos pagamentos deve ser igual ao total da venda.');

    expect(Sale::query()->count())->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
})->with([999, 1001]);

it('freezes the fee snapshot when the terminal changes after the sale', function (): void {
    $terminal = cardTerminal(['fee_percent' => 2, 'fee_fixed' => 0, 'settlement_days' => 2]);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem(5000)],
        'payments' => [['method' => 'card', 'amount' => 5000, 'terminal_id' => $terminal->id]],
    ])->assertCreated();

    $terminal->update(['fee_percent' => 9.5, 'fee_fixed' => 99, 'settlement_days' => 45]);

    $card = SalePayment::query()->firstOrFail();
    expect($card->fee_amount)->toBe(100)
        ->and($card->fee_percent)->toBe('2.00')
        ->and($card->fee_fixed_snapshot)->toBe(0)
        ->and($card->settlement_days)->toBe(2);
});

it('validates items and payments structure with pt-BR messages', function (): void {
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [['type' => 'gadget', 'source_type' => 'x', 'source_id' => '1', 'description' => 'd', 'qty' => 0, 'unit_amount' => -1]],
        'payments' => [['method' => 'boleto', 'amount' => -5]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'items.0.type', 'items.0.qty', 'items.0.unit_amount', 'payments.0.method', 'payments.0.amount',
        ])
        ->assertJsonValidationErrors([
            'items.0.type' => 'O tipo do item deve ser produto, serviço ou assinatura.',
            'items.0.qty' => 'A quantidade deve ser de pelo menos 1.',
        ]);
});

it('requires at least one item and one payment', function (): void {
    $this->postJson('/pdv/api/sales', ['operator_id' => 1, 'items' => [], 'payments' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items', 'payments']);
});

it('runs fulfillers in order product, service, subscription only for present types', function (): void {
    $log = new CallLog;
    $this->app->instance(ProductFulfiller::class, new RecordingFulfiller('product', $log));
    $this->app->instance(ServiceFulfiller::class, new RecordingFulfiller('service', $log));
    $this->app->instance(SubscriptionFulfiller::class, new RecordingFulfiller('subscription', $log));

    $subscription = array_merge(productItem(3000), ['type' => 'subscription', 'source_id' => '3']);
    $service = array_merge(productItem(2000), ['type' => 'service', 'source_id' => '4']);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [$subscription, productItem(1000), $service],
        'payments' => [['method' => 'cash', 'amount' => 6000]],
    ])->assertCreated();

    expect($log->calls)->toBe(['product', 'service', 'subscription']);

    $log->calls = [];
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [$service],
        'payments' => [['method' => 'pix', 'amount' => 2000]],
    ])->assertCreated();

    expect($log->calls)->toBe(['service']);
});

it('rolls back and returns an error when a fulfiller throws', function (): void {
    Event::fake([SaleCompleted::class]);
    $this->app->setLocale('pt_BR');
    $this->app->instance(ProductFulfiller::class, new RecordingFulfiller('product', null, true));

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem()],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertStatus(500)
        ->assertJsonPath('message', 'Não foi possível concluir a venda: falha ao processar os itens. Nenhum dado foi gravado.');

    expect(Sale::query()->count())->toBe(0)
        ->and(DB::table('pdv_sale_items')->count())->toBe(0)
        ->and(DB::table('pdv_sale_payments')->count())->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});

it('stores the optional customer reference', function (): void {
    Event::fake([SaleCompleted::class]);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'customer_type' => 'App\\Models\\Student',
        'customer_id' => '42',
        'items' => [productItem()],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertCreated();

    Event::assertDispatched(SaleCompleted::class, fn (SaleCompleted $e): bool => $e->payload->toArray()['customer'] === ['type' => 'App\\Models\\Student', 'id' => '42']);
});

it('rejects zero-amount payments', function (): void {
    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem(0)],
        'payments' => [['method' => 'cash', 'amount' => 0]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['payments.0.amount']);

    expect(Sale::query()->count())->toBe(0);
});

it('rounds card fees half-cent up using integer basis points', function (int $amount, string $percent, int $fixed, int $expectedFee): void {
    Event::fake([SaleCompleted::class]);
    $terminal = cardTerminal(['fee_percent' => $percent, 'fee_fixed' => $fixed]);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem($amount)],
        'payments' => [['method' => 'card', 'amount' => $amount, 'terminal_id' => $terminal->id]],
    ])->assertCreated()
        ->assertJsonPath('data.totals.fees', $expectedFee);

    expect(SalePayment::query()->firstOrFail()->fee_amount)->toBe($expectedFee);
})->with([
    'exact half cent rounds up (10.5)' => [1050, '1.00', 0, 11],
    'half cent at 50 cents (0.5)' => [50, '1.00', 0, 1],
    'below half cent rounds down (10.49)' => [1049, '1.00', 0, 10],
    'half cent plus fixed' => [1050, '1.00', 10, 21],
    'percent 2.675 stored as 2.68' => [10000, '2.675', 0, 268],
    'zero percent keeps only fixed' => [5000, '0.00', 25, 25],
]);

it('dispatches SaleCompleted only after the outer transaction commits', function (): void {
    $dispatched = [];
    Event::listen(SaleCompleted::class, function (SaleCompleted $event) use (&$dispatched): void {
        $dispatched[] = $event->payload->saleId;
    });

    $seenInsideOuter = null;

    DB::transaction(function () use (&$seenInsideOuter, &$dispatched): void {
        app(SaleCompletionService::class)->complete([
            'operator_id' => 1,
            'items' => [productItem()],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ]);

        $seenInsideOuter = $dispatched;
    });

    expect($seenInsideOuter)->toBe([])->and($dispatched)->toHaveCount(1);
});

it('does not dispatch SaleCompleted when the outer transaction rolls back', function (): void {
    Event::fake([SaleCompleted::class]);

    try {
        DB::transaction(function (): void {
            app(SaleCompletionService::class)->complete([
                'operator_id' => 1,
                'items' => [productItem()],
                'payments' => [['method' => 'cash', 'amount' => 1000]],
            ]);

            throw new RuntimeException('outer failure');
        });
    } catch (RuntimeException) {
    }

    expect(Sale::query()->count())->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});

it('returns 422 with the message when a fulfiller rejects the sale', function (): void {
    Event::fake([SaleCompleted::class]);
    $this->app->setLocale('pt_BR');
    $this->app->instance(ProductFulfiller::class, new class implements ProductFulfiller
    {
        public function fulfill(Sale $sale): void
        {
            throw new FulfillmentRejected('Estoque insuficiente para Kimono A2.');
        }
    });

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem()],
        'payments' => [['method' => 'cash', 'amount' => 1000]],
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Estoque insuficiente para Kimono A2.');

    expect(Sale::query()->count())->toBe(0)
        ->and(DB::table('pdv_sale_items')->count())->toBe(0)
        ->and(DB::table('pdv_sale_payments')->count())->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});

it('uses the default pt-BR message when FulfillmentRejected has none', function (): void {
    $this->app->setLocale('pt_BR');

    expect((new FulfillmentRejected)->getMessage())->toBe('A venda não pôde ser concluída: um dos itens foi recusado.');
});

it('rolls back product fulfiller side effects when a later fulfiller throws', function (): void {
    Event::fake([SaleCompleted::class]);
    $this->app->instance(ProductFulfiller::class, new class implements ProductFulfiller
    {
        public function fulfill(Sale $sale): void
        {
            Terminal::query()->create([
                'name' => 'side-effect',
                'settlement_days' => 1,
                'fee_percent' => 0,
                'fee_fixed' => 0,
            ]);
        }
    });
    $this->app->instance(ServiceFulfiller::class, new RecordingFulfiller('service', null, true));

    $service = array_merge(productItem(2000), ['type' => 'service', 'source_id' => '4']);

    $this->postJson('/pdv/api/sales', [
        'operator_id' => 1,
        'items' => [productItem(1000), $service],
        'payments' => [['method' => 'cash', 'amount' => 3000]],
    ])->assertStatus(500);

    expect(Terminal::query()->count())->toBe(0)
        ->and(Sale::query()->count())->toBe(0);
    Event::assertNotDispatched(SaleCompleted::class);
});
