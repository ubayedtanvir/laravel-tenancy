<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Resolvers;

use Illuminate\Http\Request;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;

/**
 * Tries each resolver in sequence, returning the first non-null result.
 */
final readonly class ChainTenantResolver implements TenantResolver
{
    /**
     * @param  list<TenantResolver>  $resolvers
     */
    public function __construct(private array $resolvers) {}

    /**
     * Resolve the tenant by delegating to each resolver in order.
     */
    public function resolve(Request $request): ?IsTenant
    {
        foreach ($this->resolvers as $resolver) {
            $tenant = $resolver->resolve($request);

            if ($tenant instanceof IsTenant) {
                return $tenant;
            }
        }

        return null;
    }
}
