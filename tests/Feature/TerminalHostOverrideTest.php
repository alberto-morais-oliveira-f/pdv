<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Tests\Feature;

use Am2tec\Pdv\Providers\PdvServiceProvider;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Attributes\Test;

class TerminalHostOverrideTest extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PdvServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('pdv.routes.middleware', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    #[Test]
    public function substitute_bindings_stays_when_host_replaces_middleware(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('pdv.api.terminals.update');

        $this->assertContains(SubstituteBindings::class, $route->gatherMiddleware());
    }

    #[Test]
    public function update_and_delete_of_missing_terminal_return_404(): void
    {
        $this->putJson('/pdv/api/terminals/999', ['name' => 'X'])->assertNotFound();
        $this->deleteJson('/pdv/api/terminals/999')->assertNotFound();
    }
}
