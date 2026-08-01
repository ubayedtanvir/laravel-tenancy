<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Contracts;

use Illuminate\Http\Request;

interface TenantResolver
{
    /**
     * Resolve the tenant for the given request, or return null if none can be identified.
     */
    public function resolve(Request $request): ?IsTenant;
}
