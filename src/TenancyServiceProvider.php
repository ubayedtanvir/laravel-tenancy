<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use UbayedTanvir\LaravelTenancy\Cache\TenantCache;
use UbayedTanvir\LaravelTenancy\Console\AuditCommand;
use UbayedTanvir\LaravelTenancy\Console\InstallCommand;
use UbayedTanvir\LaravelTenancy\Console\RunCommand;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;
use UbayedTanvir\LaravelTenancy\Database\EloquentTenantRepository;
use UbayedTanvir\LaravelTenancy\Database\SchemaBlueprintMixin;
use UbayedTanvir\LaravelTenancy\Database\TenantCacheInvalidator;
use UbayedTanvir\LaravelTenancy\Http\Middleware\EnsureTenantMember;
use UbayedTanvir\LaravelTenancy\Http\Middleware\IdentifyTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RecordsCurrentTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RedirectToCurrentTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RequireExplicitSwitch;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RequireTenant;
use UbayedTanvir\LaravelTenancy\Support\TenantQueueBinder;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');

        $this->app->scoped(TenancyManager::class);

        $this->app->scoped(EloquentTenantRepository::class);
        $this->app->alias(EloquentTenantRepository::class, TenantRepository::class);

        $this->app->scoped(TenantResolver::class, fn (Application $application): TenantResolver => new ResolverFactory($application)->make());
    }

    public function boot(): void
    {
        Blueprint::mixin(new SchemaBlueprintMixin);

        $this->registerMiddlewareAliases();
        $this->registerTenantCacheInvalidation();
        $this->registerQueueHooks();
        $this->registerCacheMacro();

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../config/tenancy.php' => config_path('tenancy.php')],
                ['tenancy', 'tenancy-config'],
            );

            $this->commands([
                InstallCommand::class,
                AuditCommand::class,
                RunCommand::class,
            ]);
        }
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('tenant', IdentifyTenant::class);
        $router->aliasMiddleware('tenant.required', RequireTenant::class);
        $router->aliasMiddleware('tenant.member', EnsureTenantMember::class);
        $router->aliasMiddleware('tenant.record', RecordsCurrentTenant::class);
        $router->aliasMiddleware('tenant.landing', RedirectToCurrentTenant::class);
        $router->aliasMiddleware('tenant.strict-switch', RequireExplicitSwitch::class);
    }

    private function registerTenantCacheInvalidation(): void
    {
        $model = config('tenancy.tenant.model');

        if (! \is_string($model) || ! is_subclass_of($model, Model::class)) {
            return;
        }

        $this->app->make(TenantCacheInvalidator::class)->register($model);
    }

    private function registerQueueHooks(): void
    {
        $tenantQueueBinder = new TenantQueueBinder;

        Queue::createPayloadUsing(fn (): array => $tenantQueueBinder->payload());
        Queue::before(fn (JobProcessing $jobProcessing) => $tenantQueueBinder->restore($jobProcessing));
        Queue::after(fn () => $tenantQueueBinder->reset());
        Queue::failing(fn () => $tenantQueueBinder->reset());
        Queue::exceptionOccurred(fn () => $tenantQueueBinder->reset());
        Queue::looping(fn () => $tenantQueueBinder->reset());
    }

    private function registerCacheMacro(): void
    {
        CacheRepository::macro('tenant', function (): TenantCache {
            /** @var CacheRepository $this */
            return new TenantCache($this, resolve(TenancyManager::class)->idOrFail());
        });
    }
}
