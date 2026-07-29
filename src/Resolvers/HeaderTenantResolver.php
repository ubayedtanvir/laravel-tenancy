<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Resolvers;

use Illuminate\Http\Request;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;

/**
 * Resolves the tenant from a request header.
 */
final readonly class HeaderTenantResolver implements TenantResolver
{
    public function __construct(private TenantRepository $tenantRepository) {}

    /**
     * Resolve the tenant from the configured request header.
     */
    public function resolve(Request $request): ?IsTenant
    {
        $header = config('tenancy.header', 'X-Tenant');
        $key = $request->header(\is_string($header) ? $header : 'X-Tenant');

        return \is_string($key) && $key !== ''
            ? $this->tenantRepository->findByRouteKey($key)
            : null;
    }
}
