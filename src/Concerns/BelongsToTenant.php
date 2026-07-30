<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Database\TenantScope;
use UbayedTanvir\LaravelTenancy\Exceptions\CrossTenantWriteDenied;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantOwnershipImmutable;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Scopes reads, stamps writes, and seals the tenant key.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $tenancyManager = resolve(TenancyManager::class);
            $column = $tenancyManager->foreignKeyFor($model);

            if ($tenancyManager->crossTenantEnabled()) {
                if ($model->getAttribute($column) === null) {
                    throw TenantContextMissing::forModel($model::class);
                }

                return;
            }

            if (! $tenancyManager->initialized()) {
                if (! $tenancyManager->strictModeEnabled()) {
                    return;
                }

                throw TenantContextMissing::forModel($model::class);
            }

            $current = $tenancyManager->idOrFail();
            $assigned = $model->getAttribute($column);

            if ($assigned === null) {
                $model->setAttribute($column, $current);

                return;
            }

            // A key may arrive as int from the model and string from a request;
            // compare as strings so a legitimate write is never rejected.
            if (\is_scalar($assigned) && (string) $assigned !== (string) $current) {
                throw CrossTenantWriteDenied::forModel($model, (string) $assigned, (string) $current);
            }
        });

        static::updating(function (Model $model): void {
            $tenancyManager = resolve(TenancyManager::class);

            if ($model->isDirty($tenancyManager->foreignKeyFor($model))
                && ! $tenancyManager->crossTenantEnabled()
            ) {
                throw TenantOwnershipImmutable::forModel($model);
            }
        });
    }

    /**
     * Guard the tenant foreign key against mass assignment.
     */
    public function initializeBelongsToTenant(): void
    {
        $this->mergeGuarded([$this->getTenantForeignKey()]);
    }

    public function getTenantForeignKey(): string
    {
        if (property_exists($this, 'tenantForeignKey')
            && \is_string($this->tenantForeignKey)
            && $this->tenantForeignKey !== ''
        ) {
            return $this->tenantForeignKey;
        }

        return resolve(TenancyManager::class)->foreignKey();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function tenant(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('tenancy.tenant.model');

        return $this->belongsTo($model, $this->getTenantForeignKey());
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[Scope]
    protected function withoutTenancy(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[Scope]
    protected function acrossTenants(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[Scope]
    protected function forTenant(Builder $builder, IsTenant|int|string $tenant): Builder
    {
        return $builder
            ->withoutGlobalScope(TenantScope::class)
            ->where(
                $this->qualifyColumn($this->getTenantForeignKey()),
                $tenant instanceof IsTenant ? $tenant->getKey() : $tenant,
            );
    }
}
