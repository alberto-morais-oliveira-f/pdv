<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Tests\Feature;

use Am2tec\Pdv\Providers\PdvServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Attributes\Test;

class TerminalMigrationsFlagTest extends Orchestra
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
        $app['config']->set('pdv.migrations.load', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate');
    }

    #[Test]
    public function migrations_are_not_loaded_when_flag_is_false(): void
    {
        $this->assertFalse(Schema::hasTable('pdv_terminals'));
    }
}
