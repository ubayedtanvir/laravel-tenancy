<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Contracts;

interface TenantMembership
{
    /**
     * Whether this model may access the given tenant.
     */
    public function belongsToTenant(IsTenant $isTenant): bool;
}
