<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * A cache store decorator that namespaces all keys under the current tenant.
 *
 * Obtain via Cache::tenant()
 *
 * @internal
 */
final readonly class TenantCache
{
    public function __construct(
        private Repository $repository,
        private int|string $tenantId,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        return $this->repository->put($this->key($key), $value, $ttl);
    }

    public function add(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        return $this->repository->add($this->key($key), $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->repository->forever($this->key($key), $value);
    }

    /**
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return $this->repository->remember($this->key($key), $ttl, $callback);
    }

    /**
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->repository->rememberForever($this->key($key), $callback);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->repository->pull($this->key($key), $default);
    }

    public function has(string $key): bool
    {
        return $this->repository->has($this->key($key));
    }

    public function forget(string $key): bool
    {
        return $this->repository->forget($this->key($key));
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->repository->increment($this->key($key), $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->repository->decrement($this->key($key), $value);
    }

    private function key(string $key): string
    {
        return config()->string('tenancy.cache.prefix').':'.$this->tenantId.':'.$key;
    }
}
