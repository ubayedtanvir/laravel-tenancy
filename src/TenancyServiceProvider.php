<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;
use UbayedTanvir\LaravelTenancy\Database\EloquentTenantRepository;
use UbayedTanvir\LaravelTenancy\Database\SchemaBlueprintMixin;
use UbayedTanvir\LaravelTenancy\Database\TenantCacheInvalidator;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');

        // `scoped()` bindings are flushed between requests.
        $this->app->scoped(TenancyManager::class);

        // Reachable through both the contract and the concrete class, so the
        // cache-invalidation listener and the resolvers share its memo.
        $this->app->scoped(EloquentTenantRepository::class);
        $this->app->alias(EloquentTenantRepository::class, TenantRepository::class);

        $this->app->scoped(TenantResolver::class, fn (Application $application): TenantResolver => new ResolverFactory($application)->make());
    }

    public function boot(): void
    {
        Blueprint::mixin(new SchemaBlueprintMixin);

        $this->registerTenantCacheInvalidation();

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../config/tenancy.php' => config_path('tenancy.php')],
                ['tenancy', 'tenancy-config'],
            );
        }
    }

    /**
     * Register model event listeners to invalidate the route-key cache on save and delete.
     */
    private function registerTenantCacheInvalidation(): void
    {
        $model = config('tenancy.tenant.model');

        if (! \is_string($model) || ! is_subclass_of($model, Model::class)) {
            return;
        }

        $this->app->make(TenantCacheInvalidator::class)->register($model);
    }
}
