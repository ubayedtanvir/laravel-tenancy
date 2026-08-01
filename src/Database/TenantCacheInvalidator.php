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
     * @param  class-string<Model>  $model
     */
    public function register(string $model): void
    {
        $model::saved($this->onSave(...));
        $model::deleted($this->onDelete(...));
    }

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

    private function onDelete(Model $model): void
    {
        $current = $model->getAttribute($this->routeKeyName($model));

        if (\is_string($current) || \is_int($current)) {
            $this->eloquentTenantRepository->forgetByRouteKey((string) $current);
        }
    }

    private function routeKeyName(Model $model): string
    {
        $configured = config('tenancy.tenant.route_key');

        return \is_string($configured) && $configured !== ''
            ? $configured
            : $model->getRouteKeyName();
    }
}
