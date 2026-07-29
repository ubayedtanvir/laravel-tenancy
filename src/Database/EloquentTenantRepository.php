<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Database;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;

/**
 * Eloquent-backed tenant repository with route-key caching.
 */
final class EloquentTenantRepository implements TenantRepository
{
    /**
     * @var array<string, IsTenant|null>
     */
    private array $memo = [];

    /**
     * Find a tenant by its route key, and cache the primary key.
     */
    public function findByRouteKey(string $key): ?IsTenant
    {
        if (\array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $model = TenantColumn::model();
        $keyName = $model->getKeyName();

        $configuredRouteKey = config('tenancy.tenant.route_key');
        $routeKey = \is_string($configuredRouteKey) && $configuredRouteKey !== ''
            ? $configuredRouteKey
            : $model->getRouteKeyName();

        // `false` caches the miss
        $cached = $this->store()->remember(
            $this->cacheKey($key),
            $this->ttl(),
            function () use ($model, $routeKey, $key, $keyName): int|string|false {
                $value = $model->newQuery()->where($routeKey, $key)->value($keyName);

                return \is_int($value) || \is_string($value) ? $value : false;
            },
        );

        return $this->memo[$key] = ($cached === false) ? null : $this->findByKey($cached);
    }

    /**
     * Find a tenant by its primary key.
     */
    public function findByKey(int|string $id): ?IsTenant
    {
        $tenant = TenantColumn::model()->newQuery()->whereKey($id)->first();

        return $tenant instanceof IsTenant ? $tenant : null;
    }

    /**
     * Remove a tenant from the route-key cache.
     */
    public function forget(IsTenant $isTenant): void
    {
        $this->forgetByRouteKey($isTenant->getRouteKey());
    }

    /**
     * Remove a specific route key from the cache.
     */
    public function forgetByRouteKey(string $key): void
    {
        unset($this->memo[$key]);

        $this->store()
            ->forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return 'tenancy:key:'.$key;
    }

    private function store(): Repository
    {
        $store = config('tenancy.cache.store');

        return Cache::store(\is_string($store) ? $store : null);
    }

    private function ttl(): int
    {
        $ttl = config('tenancy.cache.ttl', 3600);

        return \is_int($ttl) ? $ttl : 3600;
    }
}
