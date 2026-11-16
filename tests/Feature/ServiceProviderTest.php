<?php

declare(strict_types=1);

it('loads the pdv config through the service provider', function (): void {
    expect(config('pdv.currency'))->toBe('BRL');
});
