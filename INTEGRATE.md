# am2tec/pdv — Host integration

Leaf package: **no Composer dependency** on `financial`, `module-stock` or `store`.
Catalog, fulfillment and ledger live in the host via contracts + events.

Canonical first host: `jiujitsu` (`PdvIntegrationServiceProvider`).

---

## Path install and PHPUnit clash

Always path-install with **`symlink: false`** and **no nested `vendor/`** inside the copy the host sees.

```json
{
    "type": "path",
    "url": "/var/www/html/am2tec/pdv",
    "options": { "symlink": false }
}
```

A symlink (Composer default for path repos) exposes this package's `vendor/` — including PHPUnit — to the host autoloader. Host tests then fail with a duplicate PHPUnit subscriber (`TestFileLoadedSubscriber`).

`symlink: false` still **mirrors** a local `vendor/` if one exists in the package source. After `composer update`:

1. There must be **no** `vendor/am2tec/pdv/vendor` in the host.
2. Keep package-level `vendor/` only in the package repo for `composer test`.
3. If a nested tree appears, delete it (or rename, e.g. `vendor._bak_host_tests`) before running the host suite.

---

## `migrations.load`

Default in the package is `pdv.migrations.load = true` (`PDV_LOAD_MIGRATIONS=true`): the provider loads migrations from the vendor path.

**Stancl tenant hosts must turn this off.** Publish into the tenant folder instead:

```bash
php artisan vendor:publish --tag=pdv-config
php artisan vendor:publish --tag=pdv-migrations
# move files to database/migrations/tenant/
```

```php
// config/pdv.php (host)
'migrations' => ['load' => false],
```

jiujitsu: `load => false`; tables live in `database/migrations/tenant/2026_11_15_00000{1-4}_create_pdv_*.php`.

---

## Auth and route mounting (stancl)

The package does **not** authenticate. Defaults `['api']` / `['web']` are fail-open.

On stancl hosts, **disable auto-registration** and `require` the route files inside the tenant master group (same pattern as `module-stock`):

```php
// config/pdv.php
'routes' => ['enabled' => false, 'prefix' => 'master/pdv/api', /* auth middleware */],
'web'    => ['enabled' => false, 'prefix' => 'master/pdv', 'layout' => 'layouts.master', 'section' => 'content'],
```

```php
// routes/tenant.php — inside auth + AuthMasterMiddleware + prefix master
Route::prefix('pdv')->group(base_path('vendor/am2tec/pdv/routes/web.php'));
Route::prefix('pdv/api')->group(base_path('vendor/am2tec/pdv/routes/api.php'));
```

Publish the Blade shell (`--tag=pdv-views`) if the host layout needs `@vite` in `@stack('scripts')`.

---

## Vite

Vue 3 SFCs ship as **source**. The host compiles them.

1. `vite.config.js` input: `resources/js/pdv.js` (and `server.fs.allow` for `vendor/am2tec/pdv` if aliased).
2. Host entry:

```js
import { bootPdv } from '../../vendor/am2tec/pdv/resources/js/pdv.js';
bootPdv('#pdv-app');
```

3. Blade: `@vite(['resources/js/pdv.js'])` on the PDV pages only.

Do **not** set `resolve.preserveSymlinks` unless you are on a symlink install (this package must not be).

---

## Contracts (host binds; Nulls are the package defaults)

| Contract | Null default | Host must |
|---|---|---|
| `CatalogSearcher` | empty collection | Unified search → `CatalogItemData` (`product` \| `service` \| `subscription`) |
| `ProductFulfiller` | no-op | Reserve/decrement stock on complete |
| `SubscriptionFulfiller` | no-op | Host decides plan side-effects. jiujitsu v1 **only swaps `Student.plan_id`** (no MonthlyPayments / billing cycle) and **requires** a `Student` customer (else `FulfillmentRejected` 422). Stores `previous_plan_id` on the sale item metadata and restores it on `SaleCancelled`. |
| `ServiceFulfiller` | no-op | Record the one-off service |
| `CustomerResolver` | `null` | Resolve student/customer; sale **without** customer is allowed unless a fulfiller rejects it |

Throw `Am2tec\Pdv\Exceptions\FulfillmentRejected` from a fulfiller for a business refusal (HTTP 422, full rollback, not `report()`ed). Any other `Throwable` → 500 + rollback + `report()`.

Fulfillers run **inside** the sale transaction. `SaleCompleted` runs **after** commit (see below).

---

## Events

Dispatched with `DB::afterCommit` (`SaleCompletedPayload`, amounts in **integer cents**).

### `pdv.sale.completed` (`Am2tec\Pdv\Events\SaleCompleted`)

```json
{
  "sale_id": "uuid",
  "occurred_at": "ISO-8601",
  "operator_id": 1,
  "customer": { "type": "App\\Models\\Student", "id": "1" },
  "items": [
    {
      "type": "product|service|subscription",
      "source_type": "...",
      "source_id": "...",
      "description": "...",
      "qty": 1,
      "unit_amount": 0,
      "total_amount": 0,
      "metadata": {}
    }
  ],
  "payments": [
    {
      "method": "cash|pix|card",
      "amount": 0,
      "terminal_id": null,
      "fee_percent": null,
      "fee_amount": null,
      "settlement_days": null,
      "expected_settlement_at": null
    }
  ],
  "totals": { "gross": 0, "fees": 0, "net": 0 }
}
```

`customer` is `null` when the sale has no customer. Card fee/settlement fields are `null` on cash/PIX.

### `pdv.sale.cancelled` (`Am2tec\Pdv\Events\SaleCancelled`)

Same payload. The package does **not** reverse stock/plan/ledger — the host listens and undoes `SaleCompleted`.

### Financial (optional, host-only)

If `am2tec/financial` is installed, the host may listen; if it is not, **register nothing**. The sale already lives in `pdv_*` tables.

Suggested mapping (jiujitsu `PostSaleToFinancial`):

1. Cash/PIX → immediate wallet via `CashierService::receive` when a cash session is open.
2. Card → receivable title (`amount - fee_amount`) due `expected_settlement_at` + payable title for the fee.
3. Cancel → refund cash/PIX **only when a matching receive exists** (`PDV venda #{id}`) and cancel pending titles. Never invent a cash-out if PostSale skipped.
4. jiujitsu also undoes fulfillers on `SaleCancelled`: restock products, restore `plan_id`, delete `sellable_service_fulfillments`.

**`afterCommit` implication:** a listener cannot turn the HTTP complete into 422. jiujitsu v1 **soft-skips** cash/PIX when the cashier is closed (`report()` + log); the sale stays 201; card titles still post. A host FormRequest 422 is v2.

Feature tests that wrap the tenant in a transaction never see `afterCommit`. Fire `event(new SaleCompleted(...))` after HTTP complete (as production does after commit).

Listeners should catch/`report()` their own errors (and preferably `ShouldQueue`); an exception after commit cannot roll back the sale.

---

## Pages (v1)

| Route name | Path (jiujitsu) | Vue |
|---|---|---|
| `pdv.counter` | `GET /master/pdv/counter` | `Counter.vue` |
| `pdv.terminals` | `GET /master/pdv/terminals` | `Terminals.vue` |
| `pdv.sales` | `GET /master/pdv/sales` | `Sales.vue` |

Strings: `lang/pt_BR/ui.php` (`pdv::ui.*`).

`Counter.vue` has an optional student picker (`#pdv-customer-search` / `data-testid="pdv-customer-search"`) and sends `customer_type`/`customer_id` when one is selected. A mixed cart of **subscription + student** completes from the UI; subscription without a student still 422s (`PlanSubscriptionFulfiller`).
