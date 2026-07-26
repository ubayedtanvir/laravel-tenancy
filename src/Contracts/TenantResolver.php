<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Contracts;

use Illuminate\Http\Request;

interface TenantResolver
{
    /**
     * Resolve the tenant for the given request.
     *
     * @return IsTenant|null Null means "no tenant in this request".
     */
    public function resolve(Request $request): ?IsTenant;
}
