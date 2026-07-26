<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

/**
 * The membership side. Gives a model a many-to-many relation to tenants and a
 * default TenantMembership implementation.
 *
 * @mixin Model
 */
trait HasTenants
{
    /**
     * @return BelongsToMany<Model, $this>
     */
    public function tenants(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = config('tenancy.tenant.model');

        return $this->belongsToMany($model);
    }

    public function belongsToTenant(IsTenant $isTenant): bool
    {
        return $this->tenants()
            ->whereKey($isTenant->getKey())
            ->exists();
    }
}
