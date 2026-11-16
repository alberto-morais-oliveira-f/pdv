<?php

declare(strict_types=1);

use Am2tec\Pdv\Contracts\CatalogSearcher;
use Am2tec\Pdv\Contracts\CustomerResolver;
use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Contracts\ServiceFulfiller;
use Am2tec\Pdv\Contracts\SubscriptionFulfiller;
use Am2tec\Pdv\Data\CatalogItemData;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Support\NullCatalogSearcher;
use Am2tec\Pdv\Support\NullCustomerResolver;
use Am2tec\Pdv\Support\NullProductFulfiller;
use Am2tec\Pdv\Support\NullServiceFulfiller;
use Am2tec\Pdv\Support\NullSubscriptionFulfiller;
use Illuminate\Support\Collection;

it('returns an empty list with the default null searcher', function (): void {
    $this->getJson('/pdv/api/catalog?q=kimono')
        ->assertOk()
        ->assertExactJson([]);
});

it('returns an empty list when q is missing', function (): void {
    $this->getJson('/pdv/api/catalog')->assertOk()->assertExactJson([]);
});

it('binds null objects as defaults', function (): void {
    expect(app(CatalogSearcher::class))->toBeInstanceOf(NullCatalogSearcher::class)
        ->and(app(ProductFulfiller::class))->toBeInstanceOf(NullProductFulfiller::class)
        ->and(app(SubscriptionFulfiller::class))->toBeInstanceOf(NullSubscriptionFulfiller::class)
        ->and(app(ServiceFulfiller::class))->toBeInstanceOf(NullServiceFulfiller::class)
        ->and(app(CustomerResolver::class))->toBeInstanceOf(NullCustomerResolver::class);
});

it('null objects are no-ops', function (): void {
    $sale = new Sale;

    expect(app(CatalogSearcher::class)->search('x')->all())->toBe([])
        ->and(app(CustomerResolver::class)->resolve('App\\Models\\User', '1'))->toBeNull()
        ->and(app(CustomerResolver::class)->resolve(null, null))->toBeNull();

    app(ProductFulfiller::class)->fulfill($sale);
    app(SubscriptionFulfiller::class)->fulfill($sale);
    app(ServiceFulfiller::class)->fulfill($sale);
});

it('returns items from a host-provided searcher', function (): void {
    $this->app->bind(CatalogSearcher::class, fn (): CatalogSearcher => new class implements CatalogSearcher
    {
        public function search(string $query, int $limit = 20): Collection
        {
            return collect([
                new CatalogItemData('product', 'App\\Models\\Product', '7', "Item {$query}", 1500, ['limit' => $limit]),
            ]);
        }
    });

    $this->getJson('/pdv/api/catalog?q=gi&limit=5')
        ->assertOk()
        ->assertExactJson([[
            'type' => 'product',
            'source_type' => 'App\\Models\\Product',
            'source_id' => '7',
            'description' => 'Item gi',
            'unit_amount' => 1500,
            'metadata' => ['limit' => 5],
        ]]);
});

it('uses a default limit of 20', function (): void {
    $this->app->bind(CatalogSearcher::class, fn (): CatalogSearcher => new class implements CatalogSearcher
    {
        public function search(string $query, int $limit = 20): Collection
        {
            return collect([new CatalogItemData('service', 'S', '1', 'x', 1, ['limit' => $limit])]);
        }
    });

    $this->getJson('/pdv/api/catalog?q=a')->assertJsonPath('0.metadata.limit', 20);
});

it('rejects an invalid limit', function (mixed $limit): void {
    $this->getJson('/pdv/api/catalog?q=a&limit='.$limit)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
})->with(['zero' => [0], 'too big' => [101], 'text' => ['abc']]);

it('rejects an array q', function (): void {
    $this->getJson('/pdv/api/catalog?q[]=a')->assertUnprocessable()->assertJsonValidationErrors(['q']);
});
