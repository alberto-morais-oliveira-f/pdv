<?php

declare(strict_types=1);

use Am2tec\Pdv\Data\CatalogItemData;

it('exposes its fields and converts to array', function (): void {
    $item = new CatalogItemData(
        type: 'product',
        sourceType: 'App\\Models\\Product',
        sourceId: '42',
        description: 'Kimono A2',
        unitAmount: 29990,
        metadata: ['sku' => 'KIM-A2'],
    );

    expect($item->unitAmount)->toBe(29990)
        ->and($item->toArray())->toBe([
            'type' => 'product',
            'source_type' => 'App\\Models\\Product',
            'source_id' => '42',
            'description' => 'Kimono A2',
            'unit_amount' => 29990,
            'metadata' => ['sku' => 'KIM-A2'],
        ]);
});

it('defaults metadata to an empty array', function (): void {
    $item = new CatalogItemData('service', 'App\\Models\\Service', '1', 'Aula avulsa', 5000);

    expect($item->metadata)->toBe([])
        ->and($item->toArray()['metadata'])->toBe([]);
});
