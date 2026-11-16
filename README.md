# am2tec/pdv

Point of sale (PDV) package for Laravel multi-tenant hosts.

**Leaf package** — no Composer dependency on `financial`, `module-stock` or `store`.
Host integration (contracts, events, Vite, `migrations.load`, PHPUnit clash): **[INTEGRATE.md](INTEGRATE.md)**.

## Install

```json
"repositories": [{ "type": "path", "url": "../pdv", "options": { "symlink": false } }]
```

When this package is path-installed into a host, **do not symlink**. A symlink exposes the package's nested `vendor/` (including PHPUnit) to the host autoloader and clashes with the host's PHPUnit (`TestFileLoadedSubscriber` / duplicate subscriber error). Use `"symlink": false` so the host gets a copy. Composer still mirrors a local `vendor/` if present — strip `vendor/am2tec/pdv/vendor` after install (or avoid having `vendor/` in the source when mirroring). Package-level `vendor/` is fine for `composer test` here; just never leave that tree inside a host.

```bash
composer require am2tec/pdv
php artisan vendor:publish --tag=pdv-config
```

## Security: authentication and authorization (required)

The package does **not** authenticate or authorize anything itself. The routes are
protected only by the middleware in `pdv.routes.middleware`.

- The default `['api']` has **no auth** and is **fail-open**. It exists only for local
  development and tests.
- The host **MUST** set `pdv.routes.middleware` to include auth before exposing the
  routes, e.g. `['web', 'auth']` or `['api', 'auth:sanctum']` (plus tenancy middleware).
  Sanctum is not a dependency of this package; the host brings its own guard.
- Permission checks (who may manage terminals) are middleware-only. Add your own
  `can:` / role middleware to the same array. `FormRequest::authorize()` always returns `true`.
- `SubstituteBindings` is always appended by the provider, even if the host replaces
  the middleware array.

## Pages and Vue front (counter, terminals, sales)

The package registers three Blade pages (`pdv.counter`, `pdv.terminals`, `pdv.sales`, under
`pdv.web.prefix`, default `/pdv`). Each renders a shell with `#pdv-app` carrying `data-page`,
`data-api-base`, `data-csrf`, `data-routes` and `data-strings` (pt-BR/en strings from `lang/*/ui.php`).

- **Auth:** `pdv.web.middleware` defaults to `['web']` (no auth). The host MUST add its stack,
  e.g. `['web', 'auth', 'tenant-middleware']`, exactly like `pdv.routes.middleware`.
- **Layout:** set `pdv.web.layout` (e.g. `layouts.master`) and `pdv.web.section` (e.g. `content`) so the
  pages render inside the host layout. The default is a bare standalone HTML page. The host layout must
  load the host JS/CSS bundle (see below). Publish views with `--tag=pdv-views` to customise the shell.
- Disable the pages with `'web' => ['enabled' => false]`.

The front end is **source only**: Vue 3 SFCs in `resources/js/vue/`, compiled by the host's Vite
(`@vitejs/plugin-vue` + `vue` are host dependencies; this package ships no build).

Host `vite.config.js`:

```js
import path from 'node:path';

export default defineConfig({
    resolve: {
        alias: {
            '@am2tec/pdv': path.resolve(__dirname, 'vendor/am2tec/pdv/resources/js/pdv.js'),
        },
    },
    // add the vendor path so Vite may read files outside the project root
    server: { fs: { allow: ['.', 'vendor/am2tec/pdv'] } },
    plugins: [vue(), laravel({ input: ['resources/js/pdv.js' /* ... */] })],
});
```

Host `resources/js/pdv.js` (entry loaded only on the PDV pages, e.g. `@vite('resources/js/pdv.js')`):

```js
import { bootPdv } from '@am2tec/pdv';

bootPdv('#pdv-app');
```

Without the alias, a relative import works too:
`import { bootPdv } from '../../vendor/am2tec/pdv/resources/js/pdv.js';`.
With a path repository composer symlinks `vendor/am2tec/pdv`; set `resolve.preserveSymlinks`
only if your Vite setup resolves the real path outside the allowed `fs` roots.

`bootPdv(selector)` reads the data attributes, provides the API client (JSON, `X-CSRF-TOKEN`,
same-origin cookies) and mounts `PdvApp` with `createApp` from the host's `vue`.
Minimal styles live in `resources/css/pdv.css` (imported by `pdv.js`); the markup uses Bootstrap
class names and relies on the host theme for the look.

## Migrations

By default the provider loads the migrations from the package (`pdv.migrations.load` = `true`).
For tenant hosts, set `'migrations' => ['load' => false]` (or `PDV_LOAD_MIGRATIONS=false`)
and publish them into the tenant folder:

```bash
php artisan vendor:publish --tag=pdv-migrations
# move the files to database/migrations/tenant/
```

## Contracts (host)

Bind these in a host `PdvIntegrationServiceProvider`. Package defaults are Null no-ops.

| Contract | Responsibility |
|---|---|
| `CatalogSearcher` | Unified search → `CatalogItemData` (`product` \| `service` \| `subscription`) |
| `ProductFulfiller` | Stock reserve/decrement on complete |
| `SubscriptionFulfiller` | Host plan side-effect. jiujitsu v1: **`plan_id` swap only** (no MonthlyPayments) |
| `ServiceFulfiller` | Record one-off service |
| `CustomerResolver` | Optional customer; sale without customer is allowed in v1 |

Throw `FulfillmentRejected` to refuse a sale (HTTP 422 + rollback). See [INTEGRATE.md](INTEGRATE.md).

## Events

| Event | Name | When |
|---|---|---|
| `SaleCompleted` | `pdv.sale.completed` | After the outermost DB transaction **commits** |
| `SaleCancelled` | `pdv.sale.cancelled` | Same, on allowed cancel |

Payload (`SaleCompletedPayload`, integer cents): `sale_id`, `occurred_at`, `operator_id`, `customer`, `items[]`, `payments[]`, `totals.{gross,discount,fees,net}`, `discount.{type,percent_bps,coupon_id,coupon_code}`. Full JSON in [INTEGRATE.md](INTEGRATE.md). Financial posting is **host-only** (listen if `financial` exists; otherwise no-op).

## Completing a sale (`POST pdv/api/sales`)

- `SaleCompleted` is dispatched only **after the outermost DB transaction commits**
  (`DB::afterCommit`), so a host that wraps the call in its own transaction never notifies
  listeners (e.g. financial) about a sale that later rolls back.
- Card fees are computed with integer basis points (half-cent rounds up), never floats.
- Host fulfillers (`ProductFulfiller`, `ServiceFulfiller`, `SubscriptionFulfiller`) run inside the
  transaction. Throw `Am2tec\Pdv\Exceptions\FulfillmentRejected` to refuse the sale for a business
  reason (HTTP 422, full rollback, not reported). Any other `Throwable` is reported and
  returns HTTP 500 with a full rollback.
- **Not idempotent (known gap).** There is no `Idempotency-Key`: each successful call creates a new
  sale. Clients **must not retry blindly after a 5xx or timeout** (the sale may have committed
  before the response was lost); check for the sale first. Because the event is dispatched
  after commit, an exception thrown by a `SaleCompleted` listener surfaces after the sale is
  already saved. Host listeners should be resilient (catch/report their own errors) and
  preferably `ShouldQueue`.

## History and cancellation

- `GET pdv/api/sales?date=YYYY-MM-DD&per_page=50` lists sales of a civil day (app timezone, default
  today, any status, newest first, paginated). `GET pdv/api/sales/{id}` shows one sale.
- `DELETE pdv/api/sales/{id}` cancels the sale (the row is kept with `status=cancelled`) only when it is
  `completed`, `occurred_at` is today (app timezone) and no card payment has
  `expected_settlement_at` before the start of today. Otherwise HTTP 422 (`errors.sale`).
- `SaleCancelled` (`pdv.sale.cancelled`, same payload as `SaleCompleted`) is dispatched after the
  outermost transaction commits. The package does **not** reverse host fulfillment (stock, subscriptions,
  ledger): hosts must listen to `SaleCancelled` and undo what they did on `SaleCompleted`.

## Tests

```bash
composer install
composer test
```
