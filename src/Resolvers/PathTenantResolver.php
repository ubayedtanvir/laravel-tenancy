<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Resolvers;

use Illuminate\Http\Request;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;

final readonly class PathTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?IsTenant
    {
        // TODO: Implement resolve() method.
        return null;
    }
}
