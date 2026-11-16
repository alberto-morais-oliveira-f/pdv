<?php

declare(strict_types=1);

use Am2tec\Pdv\Enums\SaleItemType;
use Am2tec\Pdv\Enums\SaleStatus;
use Am2tec\Pdv\Tests\Support\SaleBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-15 15:30:00', config('app.timezone')));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('lists today sales by default, newest first, including cancelled ones', function (): void {
    $morning = SaleBuilder::make(now()->setTime(9, 0));
    $afternoon = SaleBuilder::make(now()->setTime(14, 0));
    $cancelled = SaleBuilder::make(now()->setTime(11, 0), SaleStatus::Cancelled);
    SaleBuilder::make(now()->subDay()->setTime(23, 59, 59));
    SaleBuilder::make(now()->addDay()->startOfDay());

    $response = $this->getJson('/pdv/api/sales')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$afternoon->id, $cancelled->id, $morning->id]);
    $response->assertJsonPath('data.0.items.0.description', 'Kimono A2')
        ->assertJsonPath('data.0.payments.0.method', 'cash')
        ->assertJsonPath('data.1.status', 'cancelled');
});

it('filters by date using the app timezone day boundaries', function (): void {
    $first = SaleBuilder::make(Carbon::parse('2026-11-10 00:00:00', config('app.timezone')));
    $last = SaleBuilder::make(Carbon::parse('2026-11-10 23:59:59', config('app.timezone')));
    SaleBuilder::make(Carbon::parse('2026-11-11 00:00:00', config('app.timezone')));
    SaleBuilder::make(Carbon::parse('2026-11-09 23:59:59', config('app.timezone')));

    $response = $this->getJson('/pdv/api/sales?date=2026-11-10')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$last->id, $first->id]);
});

it('returns an empty list when there are no sales on the day', function (): void {
    $this->getJson('/pdv/api/sales?date=2026-01-01')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('paginates the listing', function (): void {
    $day = Carbon::parse('2026-11-10', config('app.timezone'));
    foreach (range(1, 3) as $hour) {
        SaleBuilder::make($day->copy()->setTime($hour, 0));
    }

    $response = $this->getJson('/pdv/api/sales?date=2026-11-10&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);

    expect($response->json('links.next'))
        ->toContain('date=2026-11-10')
        ->toContain('per_page=2');
});

it('rejects an invalid date or page size with pt-BR messages', function (string $query, string $field): void {
    $this->getJson("/pdv/api/sales?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'bad format' => ['date=15/11/2026', 'date'],
    'not a date' => ['date=2026-13-45', 'date'],
    'per_page too big' => ['per_page=1000', 'per_page'],
    'per_page zero' => ['per_page=0', 'per_page'],
]);

it('shows a sale with items and payments', function (): void {
    $sale = SaleBuilder::make(now(), card: []);

    $this->getJson("/pdv/api/sales/{$sale->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $sale->id)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.items.0.total_amount', 1000)
        ->assertJsonPath('data.payments.0.method', 'card')
        ->assertJsonPath('data.payments.0.fee_amount', 40)
        ->assertJsonPath('data.totals.net', 1000);
});

it('shows a past or cancelled sale too', function (): void {
    $old = SaleBuilder::make(now()->subDays(10));
    $cancelled = SaleBuilder::make(now(), SaleStatus::Cancelled);

    $this->getJson("/pdv/api/sales/{$old->id}")->assertOk()->assertJsonPath('data.status', 'completed');
    $this->getJson("/pdv/api/sales/{$cancelled->id}")->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('lists recent sales for a customer across days, newest first', function (): void {
    $older = SaleBuilder::make(now()->subDays(3));
    $older->update(['customer_type' => 'App\\Models\\Student', 'customer_id' => '42']);
    $newer = SaleBuilder::make(now()->subDay());
    $newer->update(['customer_type' => 'App\\Models\\Student', 'customer_id' => '42']);
    $other = SaleBuilder::make(now());
    $other->update(['customer_type' => 'App\\Models\\Student', 'customer_id' => '99']);
    SaleBuilder::make(now()->subDays(2));

    $response = $this->getJson('/pdv/api/sales?customer_type='.urlencode('App\\Models\\Student').'&customer_id=42&limit=5')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(array_column($response->json('data'), 'id'))->toBe([$newer->id, $older->id]);
});

it('caps customer history by limit', function (): void {
    foreach (range(1, 3) as $hour) {
        $sale = SaleBuilder::make(now()->subDays($hour)->setTime(10, 0));
        $sale->update(['customer_type' => 'Student', 'customer_id' => '7']);
    }

    $this->getJson('/pdv/api/sales?customer_type=Student&customer_id=7&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('rejects a customer filter missing its pair', function (): void {
    $this->getJson('/pdv/api/sales?customer_id=42')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_type']);
});

it('combines customer filters with an optional date range', function (): void {
    $today = SaleBuilder::make(now());
    $today->update(['customer_type' => 'Student', 'customer_id' => '42']);
    $yesterday = SaleBuilder::make(now()->subDay());
    $yesterday->update(['customer_type' => 'Student', 'customer_id' => '42']);
    $other = SaleBuilder::make(now());
    $other->update(['customer_type' => 'Student', 'customer_id' => '99']);

    $withDate = $this->getJson('/pdv/api/sales?customer_type=Student&customer_id=42&date=2026-11-15')
        ->assertOk();

    expect(array_column($withDate->json('data'), 'id'))->toBe([$today->id]);

    $history = $this->getJson('/pdv/api/sales?customer_type=Student&customer_id=42&limit=5')
        ->assertOk();

    expect(array_column($history->json('data'), 'id'))->toBe([$today->id, $yesterday->id]);
});

it('filters by an inclusive from/to civil-day range', function (): void {
    $early = SaleBuilder::make(Carbon::parse('2026-11-10 10:00:00', config('app.timezone')));
    $mid = SaleBuilder::make(Carbon::parse('2026-11-11 23:59:59', config('app.timezone')));
    SaleBuilder::make(Carbon::parse('2026-11-12 00:00:00', config('app.timezone')));
    SaleBuilder::make(Carbon::parse('2026-11-09 23:59:59', config('app.timezone')));

    $response = $this->getJson('/pdv/api/sales?from=2026-11-10&to=2026-11-11')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$mid->id, $early->id]);
});

it('filters by payment_method via payments', function (): void {
    $cash = SaleBuilder::make(now());
    $card = SaleBuilder::make(now(), card: []);

    $response = $this->getJson('/pdv/api/sales?payment_method=card')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$card->id])
        ->and($response->json('data.0.payments.0.method'))->toBe('card')
        ->and($cash->id)->not->toBe($card->id);
});

it('filters by item_type via items', function (): void {
    $product = SaleBuilder::make(now());
    $subscription = SaleBuilder::make(now()->setTime(14, 0));
    $subscription->items()->first()->update(['type' => SaleItemType::Subscription]);

    $response = $this->getJson('/pdv/api/sales?item_type=subscription')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$subscription->id])
        ->and($product->id)->not->toBe($subscription->id);
});

it('filters by q with a case-insensitive match on item description', function (): void {
    $kimono = SaleBuilder::make(now());
    $belt = SaleBuilder::make(now()->setTime(14, 0));
    $belt->items()->first()->update(['description' => 'Faixa Preta']);

    $response = $this->getJson('/pdv/api/sales?q=kImOnO')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$kimono->id]);
});

it('filters by item metadata category', function (): void {
    $plans = SaleBuilder::make(now());
    $plans->items()->first()->update(['metadata' => ['category' => 'Planos']]);
    $products = SaleBuilder::make(now()->setTime(14, 0));
    $products->items()->first()->update(['metadata' => ['category' => 'Produtos']]);

    $response = $this->getJson('/pdv/api/sales?category=Planos')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$plans->id]);
});

it('filters by status, operator_id and terminal_id', function (): void {
    $completed = SaleBuilder::make(now()->setTime(9, 0));
    $cancelled = SaleBuilder::make(now()->setTime(10, 0), SaleStatus::Cancelled);
    $otherOperator = SaleBuilder::make(now()->setTime(11, 0));
    $otherOperator->update(['operator_id' => 9]);
    $card = SaleBuilder::make(now()->setTime(12, 0), card: []);
    $otherCard = SaleBuilder::make(now()->setTime(13, 0), card: []);

    $this->getJson('/pdv/api/sales?status=cancelled')
        ->assertOk()
        ->assertJsonPath('data.0.id', $cancelled->id)
        ->assertJsonCount(1, 'data');

    $this->getJson('/pdv/api/sales?operator_id=9')
        ->assertOk()
        ->assertJsonPath('data.0.id', $otherOperator->id)
        ->assertJsonCount(1, 'data');

    $terminalId = $card->payments->first()->terminal_id;

    $this->getJson('/pdv/api/sales?terminal_id='.$terminalId)
        ->assertOk()
        ->assertJsonPath('data.0.id', $card->id)
        ->assertJsonCount(1, 'data');

    expect($completed->id)->not->toBe($otherCard->id);
});

it('rejects invalid list filters with 422', function (string $query, string $field): void {
    $this->getJson("/pdv/api/sales?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'bad payment method' => ['payment_method=cheque', 'payment_method'],
    'bad item type' => ['item_type=foo', 'item_type'],
    'bad status' => ['status=pending', 'status'],
    'to before from' => ['from=2026-11-12&to=2026-11-10', 'to'],
]);

it('returns 404 when showing an unknown or malformed id', function (string $id): void {
    $this->getJson("/pdv/api/sales/{$id}")->assertNotFound();
})->with([
    'unknown uuid' => '0b2f6b1e-0000-4000-8000-000000000000',
    'not a uuid' => 'abc',
]);
