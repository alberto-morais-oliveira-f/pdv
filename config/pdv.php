<?php

declare(strict_types=1);

return [
    /*
    | Currency used by the PDV. Monetary values are stored in cents.
    */
    'currency' => env('PDV_CURRENCY', 'BRL'),

    /*
    | API routes. Set `enabled` to false to register your own routes.
    | `middleware` is where the host MUST add its auth stack, e.g. ['api', 'auth:sanctum'].
    | The default ['api'] has NO authentication (local/tests only).
    | SubstituteBindings is always appended by the provider.
    */
    'routes' => [
        'enabled' => true,
        'prefix' => env('PDV_ROUTES_PREFIX', 'pdv/api'),
        'middleware' => ['api'],
    ],

    /*
    | Blade pages (counter, terminals, coupons, sales) that mount the Vue app.
    | `middleware` is where the host MUST add auth, e.g. ['web', 'auth'] (the default has none).
    | `layout` is the host Blade layout the pages extend (e.g. 'layouts.master'); `section` is the
    | section that layout yields for the body. The default layout is a bare standalone HTML shell.
    */
    'web' => [
        'enabled' => true,
        'prefix' => env('PDV_WEB_PREFIX', 'pdv'),
        'middleware' => ['web'],
        'layout' => 'pdv::layout',
        'section' => 'content',
    ],

    /*
    | Load the package migrations from the vendor path. Set to false when the host
    | publishes them (tag `pdv-migrations`) into its own tenant migrations folder.
    */
    'migrations' => [
        'load' => env('PDV_LOAD_MIGRATIONS', true),
    ],
];
