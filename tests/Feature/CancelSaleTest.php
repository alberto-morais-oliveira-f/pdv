<?php

declare(strict_types=1);

use Am2tec\Pdv\Enums\SaleStatus;
use Am2tec\Pdv\Events\SaleCancelled;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Services\SaleCancellationService;
use Am2tec\Pdv\Tests\Support\SaleBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-15 15:30:00', config('app.timezone')));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('cancels a completed sale from today and dispatches SaleCancelled', function (): void {
    Event::fake([SaleCancelled::class]);
    $sale = SaleBuilder::make(now()->subHours(3));

    $this->deleteJson("/pdv/api/sales/{$sale->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $sale->id)
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.totals.gross', 1000);

    expect($sale->fresh()->status)->toBe(SaleStatus::Cancelled);

    Event::assertDispatchedTimes(SaleCancelled::class, 1);
    Event::assertDispatched(SaleCancelled::class, function (SaleCancelled $event) use ($sale): bool {
        $payload = $event->payload->toArray();

        return $payload['sale_id'] === $sale->id
            && $payload['totals'] === ['gross' => 1000, 'discount' => 0, 'fees' => 0, 'net' => 1000]
            && $payload['items'][0]['source_id'] === '10'
            && $payload['payments'][0]['method'] === 'cash';
    });
});

it('cancels a card sale settling in the future', function (): void {
    $sale = SaleBuilder::make(now(), card: []);

    $this->deleteJson("/pdv/api/sales/{$sale->id}")->assertOk();

    expect($sale->fresh()->status)->toBe(SaleStatus::Cancelled);
});

it('cancels a card sale whose settlement is today', function (): void {
    $sale = SaleBuilder::make(now(), card: ['expected_settlement_at' => now()->startOfDay()]);

    $this->deleteJson("/pdv/api/sales/{$sale->id}")->assertOk();

    expect($sale->fresh()->status)->toBe(SaleStatus::Cancelled);
});

it('rejects cancelling a sale from a previous day', function (): void {
    $this->app->setLocale('pt_BR');
    Event::fake([SaleCancelled::class]);
    $sale = SaleBuilder::make(now()->subDay());

    $this->deleteJson("/pdv/api/sales/{$sale->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.sale.0', 'Só é possível cancelar vendas realizadas hoje.');

    expect($sale->fresh()->status)->toBe(SaleStatus::Completed);
    Event::assertNotDispatched(SaleCancelled::class);
});

it('uses the app timezone civil date, not UTC, to decide what is today', function (): void {
    $original = date_default_timezone_get();
    config(['app.timezone' => 'America/Sao_Paulo']);
    date_default_timezone_set('America/Sao_Paulo');
    Carbon::setTestNow(Carbon::parse('2026-11-15 22:00:00', 'America/Sao_Paulo')); // 2026-11-16 01:00 UTC

    $sameCivilDay = SaleBuilder::make(Carbon::parse('2026-11-15 00:30:00', 'America/Sao_Paulo'));
    $yesterdayCivil = SaleBuilder::make(Carbon::parse('2026-11-14 23:59:00', 'America/Sao_Paulo'));

    $this->deleteJson("/pdv/api/sales/{$sameCivilDay->id}")->assertOk();
    $this->deleteJson("/pdv/api/sales/{$yesterdayCivil->id}")->assertUnprocessable();

    date_default_timezone_set($original);
});

it('rejects cancelling when a card payment already passed its settlement date', function (): void {
    $this->app->setLocale('pt_BR');
    Event::fake([SaleCancelled::class]);
    $sale = SaleBuilder::make(now(), card: ['expected_settlement_at' => now()->subDay()->startOfDay()]);

    $this->deleteJson("/pdv/api/sales/{$sale->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.sale.0', 'Não é possível cancelar: um pagamento no cartão já foi liquidado.');

    expect($sale->fresh()->status)->toBe(SaleStatus::Completed);
    Event::assertNotDispatched(SaleCancelled::class);
});

it('rejects cancelling a sale that is already cancelled without dispatching again', function (): void {
    $this->app->setLocale('pt_BR');
    Event::fake([SaleCancelled::class]);
    $sale = SaleBuilder::make(now(), SaleStatus::Cancelled);

    $this->deleteJson("/pdv/api/sales/{$sale->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.sale.0', 'Só é possível cancelar vendas concluídas.');

    Event::assertNotDispatched(SaleCancelled::class);
});

it('cancels only once when called twice', function (): void {
    Event::fake([SaleCancelled::class]);
    $sale = SaleBuilder::make(now());

    $this->deleteJson("/pdv/api/sales/{$sale->id}")->assertOk();
    $this->deleteJson("/pdv/api/sales/{$sale->id}")->assertUnprocessable();

    Event::assertDispatchedTimes(SaleCancelled::class, 1);
});

it('rejects cancelling a draft sale', function (): void {
    $sale = SaleBuilder::make(now(), SaleStatus::Draft);

    $this->deleteJson("/pdv/api/sales/{$sale->id}")->assertUnprocessable();

    expect($sale->fresh()->status)->toBe(SaleStatus::Draft);
});

it('returns 404 for an unknown or malformed sale id', function (string $id): void {
    $this->deleteJson("/pdv/api/sales/{$id}")->assertNotFound();
})->with([
    'unknown uuid' => '0b2f6b1e-0000-4000-8000-000000000000',
    'not a uuid' => 'abc',
]);

it('dispatches SaleCancelled only after the outer transaction commits', function (): void {
    $sale = SaleBuilder::make(now());
    $dispatched = 0;
    Event::listen(SaleCancelled::class, function () use (&$dispatched): void {
        $dispatched++;
    });

    $seenInside = null;
    DB::transaction(function () use ($sale, &$seenInside, &$dispatched): void {
        app(SaleCancellationService::class)->cancel($sale);
        $seenInside = $dispatched;
    });

    expect($seenInside)->toBe(0)->and($dispatched)->toBe(1);
});

it('does not dispatch SaleCancelled when the outer transaction rolls back', function (): void {
    Event::fake([SaleCancelled::class]);
    $sale = SaleBuilder::make(now());

    try {
        DB::transaction(function () use ($sale): void {
            app(SaleCancellationService::class)->cancel($sale);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(Sale::query()->findOrFail($sale->id)->status)->toBe(SaleStatus::Completed);
    Event::assertNotDispatched(SaleCancelled::class);
});

it('re-checks the persisted state, not a stale model', function (): void {
    Event::fake([SaleCancelled::class]);
    $stale = SaleBuilder::make(now());
    Sale::query()->whereKey($stale->id)->update(['status' => SaleStatus::Cancelled->value]);

    expect(fn () => app(SaleCancellationService::class)->cancel($stale))
        ->toThrow(ValidationException::class);

    Event::assertNotDispatched(SaleCancelled::class);
});
