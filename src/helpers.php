<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\TenancyManager;

if (! function_exists('tenant')) {
    /**
     * The currently bound tenant, or null.
     */
    function tenant(): ?IsTenant
    {
        return resolve(TenancyManager::class)->current();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * The currently bound tenant's key, or null.
     */
    function tenant_id(): int|string|null
    {
        return resolve(TenancyManager::class)->id();
    }
}
