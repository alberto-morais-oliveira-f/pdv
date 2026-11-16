<?php

declare(strict_types=1);

use Am2tec\Pdv\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a terminal', function (): void {
    $response = $this->postJson('/pdv/api/terminals', [
        'name' => 'Maquininha Balcão',
        'settlement_days' => 30,
        'fee_percent' => 2.99,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Maquininha Balcão')
        ->assertJsonPath('data.settlement_days', 30)
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.fee_fixed', 0);

    expect((float) $response->json('data.fee_percent'))->toBe(2.99);

    $this->assertDatabaseHas('pdv_terminals', [
        'name' => 'Maquininha Balcão',
        'settlement_days' => 30,
        'fee_fixed' => 0,
        'active' => true,
    ]);
});

it('stores fee_fixed as integer cents', function (): void {
    $this->postJson('/pdv/api/terminals', [
        'name' => 'Terminal',
        'settlement_days' => 1,
        'fee_percent' => 1.5,
        'fee_fixed' => 50,
    ])->assertCreated()->assertJsonPath('data.fee_fixed', 50);
});

it('lists terminals', function (): void {
    Terminal::query()->create(['name' => 'A', 'settlement_days' => 1, 'fee_percent' => 1]);
    Terminal::query()->create(['name' => 'B', 'settlement_days' => 2, 'fee_percent' => 2]);

    $this->getJson('/pdv/api/terminals')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'A');
});

it('returns an empty list when there are no terminals', function (): void {
    $this->getJson('/pdv/api/terminals')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('updates a terminal', function (): void {
    $terminal = Terminal::query()->create(['name' => 'Old', 'settlement_days' => 1, 'fee_percent' => 1]);

    $this->putJson("/pdv/api/terminals/{$terminal->id}", [
        'name' => 'New',
        'settlement_days' => 15,
        'fee_percent' => 3.5,
        'fee_fixed' => 100,
        'active' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.settlement_days', 15)
        ->assertJsonPath('data.fee_fixed', 100)
        ->assertJsonPath('data.active', false);

    $this->assertDatabaseHas('pdv_terminals', ['id' => $terminal->id, 'name' => 'New', 'active' => false]);
});

it('returns 404 when updating a missing terminal', function (): void {
    $this->putJson('/pdv/api/terminals/999', [
        'name' => 'X',
        'settlement_days' => 1,
        'fee_percent' => 1,
    ])->assertNotFound();
});

it('deletes a terminal', function (): void {
    $terminal = Terminal::query()->create(['name' => 'Gone', 'settlement_days' => 1, 'fee_percent' => 1]);

    $this->deleteJson("/pdv/api/terminals/{$terminal->id}")->assertNoContent();

    $this->assertSoftDeleted('pdv_terminals', ['id' => $terminal->id]);
    $this->getJson('/pdv/api/terminals')->assertJsonCount(0, 'data');
});

it('rejects invalid fee_percent with pt-BR messages', function (mixed $value, string $message): void {
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/terminals', [
        'name' => 'Terminal',
        'settlement_days' => 1,
        'fee_percent' => $value,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_percent'])
        ->assertJsonPath('errors.fee_percent.0', $message);
})->with([
    'negative' => [-1, 'A taxa percentual não pode ser negativa.'],
    'above 100' => [100.5, 'A taxa percentual não pode ser maior que 100%.'],
    'not numeric' => ['abc', 'A taxa percentual deve ser um número.'],
]);

it('returns the pt-BR message for a missing name', function (): void {
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/terminals', [
        'settlement_days' => 1,
        'fee_percent' => 1,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'O nome do terminal é obrigatório.');
});

it('rejects invalid settlement_days and fee_fixed', function (): void {
    $this->postJson('/pdv/api/terminals', [
        'name' => 'T',
        'settlement_days' => -1,
        'fee_percent' => 1,
        'fee_fixed' => 1.5,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['settlement_days', 'fee_fixed']);
});

it('registers routes with the configured prefix and middleware', function (): void {
    $route = app('router')->getRoutes()->getByName('pdv.api.terminals.index');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('pdv/api/terminals')
        ->and($route->gatherMiddleware())->toContain('api');
});

it('returns the pt-BR message for fee_percent above 100', function (): void {
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/terminals', [
        'name' => 'T',
        'settlement_days' => 1,
        'fee_percent' => 150,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.fee_percent.0', 'A taxa percentual não pode ser maior que 100%.');
});

it('rejects fee_fixed above the unsigned integer column limit', function (): void {
    $this->app->setLocale('pt_BR');

    $this->postJson('/pdv/api/terminals', [
        'name' => 'T',
        'settlement_days' => 1,
        'fee_percent' => 1,
        'fee_fixed' => 4294967296,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.fee_fixed.0', 'A taxa fixa não pode ser maior que 4294967295 centavos.');

    $terminal = Terminal::query()->create(['name' => 'U', 'settlement_days' => 1, 'fee_percent' => 1]);

    $this->putJson("/pdv/api/terminals/{$terminal->id}", ['fee_fixed' => 4294967296])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_fixed']);
});
