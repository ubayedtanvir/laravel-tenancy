<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Resolvers;

use Illuminate\Http\Request;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;

/**
 * Resolves the tenant from a named route parameter.
 */
final readonly class PathTenantResolver implements TenantResolver
{
    public function __construct(private TenantRepository $tenantRepository) {}

    public function resolve(Request $request): ?IsTenant
    {
        $parameter = config('tenancy.route_parameter', 'tenant');
        $key = $request->route(\is_string($parameter) ? $parameter : 'tenant');

        if ($key instanceof IsTenant) {
            return $key;
        }

        return \is_string($key) && $key !== ''
            ? $this->tenantRepository->findByRouteKey($key)
            : null;
    }
}
