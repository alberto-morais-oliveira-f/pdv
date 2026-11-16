<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;

beforeEach(function (): void {
    $this->actingAs(new GenericUser(['id' => 7]));
});

it('renders each page shell with the vue mount point and api base', function (string $routeName, string $uri, string $page): void {
    expect(route($routeName, absolute: false))->toBe($uri);

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('id="pdv-app"', false)
        ->assertSee('data-api-base="'.url('pdv/api').'"', false)
        ->assertSee('data-page="'.$page.'"', false)
        ->assertSee('data-csrf="', false);
})->with([
    'counter' => ['pdv.counter', '/pdv/counter', 'counter'],
    'terminals' => ['pdv.terminals', '/pdv/terminals', 'terminals'],
    'sales' => ['pdv.sales', '/pdv/sales', 'sales'],
]);

it('exposes the csrf token and pt-BR strings to the front', function (): void {
    app()->setLocale('pt_BR');

    $this->get(route('pdv.counter'))
        ->assertOk()
        ->assertSee('data-csrf="'.csrf_token().'"', false)
        ->assertSee('Finalizar venda');
});

it('uses the api prefix from config for data-api-base', function (): void {
    config()->set('pdv.routes.prefix', 'custom/api');

    $this->get(route('pdv.sales'))
        ->assertOk()
        ->assertSee('data-api-base="'.url('custom/api').'"', false);
});

it('ships routes and strings as valid json attributes', function (): void {
    app()->setLocale('pt_BR');

    $html = $this->get(route('pdv.counter'))->assertOk()->getContent();

    preg_match('/data-strings="([^"]*)"/', $html, $strings);
    preg_match('/data-routes="([^"]*)"/', $html, $routes);

    expect(json_decode(html_entity_decode($strings[1]), true)['counter']['finalize'])->toBe('Finalizar venda')
        ->and(json_decode(html_entity_decode($routes[1]), true)['sales'])->toBe(route('pdv.sales'));
});
