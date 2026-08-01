<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use UbayedTanvir\LaravelTenancy\TenancyManager;
use UbayedTanvir\LaravelTenancy\TenancyServiceProvider;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TenancyServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Required by middleware tests that use the 'web' group (session encryption).
        $app->make(Repository::class)->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app->make(Repository::class)->set('tenancy.tenant.model', Tenant::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function tearDown(): void
    {
        if ($this->app?->resolved(TenancyManager::class)) {
            $this->app->make(TenancyManager::class)->end();
        }

        parent::tearDown();
    }
}
