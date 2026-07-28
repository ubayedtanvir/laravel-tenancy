<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use UbayedTanvir\LaravelTenancy\Database\TenancyBlueprintMacros;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');

        // `scoped()` bindings are flushed between requests.
        $this->app->scoped(TenancyManager::class);
    }

    public function boot(): void
    {
        Blueprint::mixin(new TenancyBlueprintMacros);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../config/tenancy.php' => config_path('tenancy.php')],
                ['tenancy', 'tenancy-config'],
            );
        }
    }
}
