<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Providers;

use Am2tec\Pdv\Contracts\CatalogSearcher;
use Am2tec\Pdv\Contracts\CustomerResolver;
use Am2tec\Pdv\Contracts\ProductFulfiller;
use Am2tec\Pdv\Contracts\ServiceFulfiller;
use Am2tec\Pdv\Contracts\SubscriptionFulfiller;
use Am2tec\Pdv\Support\NullCatalogSearcher;
use Am2tec\Pdv\Support\NullCustomerResolver;
use Am2tec\Pdv\Support\NullProductFulfiller;
use Am2tec\Pdv\Support\NullServiceFulfiller;
use Am2tec\Pdv\Support\NullSubscriptionFulfiller;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PdvServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/pdv.php', 'pdv');

        $this->registerDefaultBindings();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'pdv');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'pdv');
        if (config('pdv.migrations.load', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->registerRoutes();
        $this->registerWebRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/pdv.php' => config_path('pdv.php'),
            ], 'pdv-config');

            $this->publishes([
                __DIR__.'/../../database/migrations' => database_path('migrations'),
            ], 'pdv-migrations');

            $this->publishes([
                __DIR__.'/../../lang' => $this->app->langPath('vendor/pdv'),
            ], 'pdv-lang');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/pdv'),
            ], 'pdv-views');
        }
    }

    /**
     * Null Objects so the package works out of the box; hosts rebind to integrate.
     */
    private function registerDefaultBindings(): void
    {
        $this->app->bindIf(CatalogSearcher::class, NullCatalogSearcher::class);
        $this->app->bindIf(ProductFulfiller::class, NullProductFulfiller::class);
        $this->app->bindIf(SubscriptionFulfiller::class, NullSubscriptionFulfiller::class);
        $this->app->bindIf(ServiceFulfiller::class, NullServiceFulfiller::class);
        $this->app->bindIf(CustomerResolver::class, NullCustomerResolver::class);
    }

    private function registerRoutes(): void
    {
        if (! config('pdv.routes.enabled', true)) {
            return;
        }

        /*
         * SubstituteBindings is always applied: without it, a host that replaces
         * `pdv.routes.middleware` would get an empty model injected and PUT/DELETE
         * would silently succeed on a non-existent record.
         */
        $middleware = array_values(array_unique([
            ...(array) config('pdv.routes.middleware', ['api']),
            SubstituteBindings::class,
        ], SORT_REGULAR));

        Route::prefix((string) config('pdv.routes.prefix', 'pdv/api'))
            ->middleware($middleware)
            ->group(__DIR__.'/../../routes/api.php');
    }

    /**
     * Blade shells that mount the Vue app. The host MUST add its auth stack to
     * `pdv.web.middleware` (same warning as the API routes).
     */
    private function registerWebRoutes(): void
    {
        if (! config('pdv.web.enabled', true)) {
            return;
        }

        Route::prefix((string) config('pdv.web.prefix', 'pdv'))
            ->middleware((array) config('pdv.web.middleware', ['web']))
            ->group(__DIR__.'/../../routes/web.php');
    }
}
