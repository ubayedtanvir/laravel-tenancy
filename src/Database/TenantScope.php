<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 *
 * @internal
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenancyManager = resolve(TenancyManager::class);

        if ($tenancyManager->crossTenantEnabled()) {
            return;
        }

        if (! $tenancyManager->initialized()) {
            if ($tenancyManager->strictModeEnabled()) {
                throw TenantContextMissing::forModel($model::class);
            }

            // Lenient mode is a migration aid, not an operating mode.
            LenientModeWarning::emitOnce($model::class);

            return;
        }

        // Qualified to avoid ambiguity when joining multiple scoped tables.
        $builder->where(
            $model->qualifyColumn($tenancyManager->foreignKeyFor($model)),
            $tenancyManager->idOrFail(),
        );
    }
}
