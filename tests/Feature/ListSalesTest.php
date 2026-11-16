<?php

declare(strict_types=1);

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

it('returns 404 when showing an unknown or malformed id', function (string $id): void {
    $this->getJson("/pdv/api/sales/{$id}")->assertNotFound();
})->with([
    'unknown uuid' => '0b2f6b1e-0000-4000-8000-000000000000',
    'not a uuid' => 'abc',
]);
