<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Database;

use Illuminate\Database\Eloquent\Model;

/**
 * Listens to tenant model events and evicts stale route-key cache entries.
 */
final readonly class TenantCacheInvalidator
{
    public function __construct(private EloquentTenantRepository $eloquentTenantRepository) {}

    /**
     * Register saved and deleted listeners on the given tenant model class.
     *
     * @param  class-string<Model>  $model
     */
    public function register(string $model): void
    {
        $model::saved($this->onSave(...));
        $model::deleted($this->onDelete(...));
    }

    /**
     * Flush the cached route keys for a tenant after it is saved.
     */
    private function onSave(Model $model): void
    {
        $routeKeyName = $this->routeKeyName($model);

        $current = $model->getAttribute($routeKeyName);

        if (\is_string($current) || \is_int($current)) {
            $this->eloquentTenantRepository->forgetByRouteKey((string) $current);
        }

        $original = $model->getOriginal($routeKeyName);

        if ((\is_string($original) || \is_int($original)) && $original !== $current) {
            $this->eloquentTenantRepository->forgetByRouteKey((string) $original);
        }
    }

    /**
     * Flush the cached route key for a tenant after it is deleted.
     */
    private function onDelete(Model $model): void
    {
        $current = $model->getAttribute($this->routeKeyName($model));

        if (\is_string($current) || \is_int($current)) {
            $this->eloquentTenantRepository->forgetByRouteKey((string) $current);
        }
    }

    /**
     * Get the route key attribute name for the tenant model.
     */
    private function routeKeyName(Model $model): string
    {
        $configured = config('tenancy.tenant.route_key');

        return \is_string($configured) && $configured !== ''
            ? $configured
            : $model->getRouteKeyName();
    }
}
