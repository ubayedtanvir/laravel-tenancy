<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy;

use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../config/tenancy.php' => config_path('tenancy.php')],
                ['tenancy', 'tenancy-config'],
            );
        }
    }
}
