<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * @method static void initialize(IsTenant $tenant)
 * @method static void end()
 * @method static IsTenant|null current()
 * @method static IsTenant currentOrFail()
 * @method static int|string|null id()
 * @method static int|string idOrFail()
 * @method static bool initialized()
 * @method static bool is(IsTenant|int|string $tenant)
 * @method static bool strictModeEnabled()
 * @method static bool crossTenantEnabled()
 * @method static mixed crossTenant(Closure $callback)
 * @method static mixed runFor(IsTenant $tenant, Closure $callback)
 * @method static void each(Closure $callback, int $chunk = 100)
 * @method static void resolveUsing(Closure $callback)
 * @method static Closure|null customResolver()
 * @method static void shouldQueueBeTenantAware(bool $value = true)
 * @method static bool queueTenantAware()
 * @method static string foreignKey()
 *
 * @see TenancyManager
 */
final class Tenancy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenancyManager::class;
    }
}
