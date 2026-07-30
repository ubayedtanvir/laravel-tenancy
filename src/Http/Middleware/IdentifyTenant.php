<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;
use UbayedTanvir\LaravelTenancy\Events\TenantResolutionFailed;
use UbayedTanvir\LaravelTenancy\Exceptions\TenancyException;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Resolve the current tenant from the request and bind it to the manager.
 */
final readonly class IdentifyTenant
{
    public function __construct(
        private TenantResolver $tenantResolver,
        private TenancyManager $tenancyManager,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        throw_if(
            ! app()->isProduction() && $this->bindingsAlreadySubstituted($request),
            TenancyException::class,
            'IdentifyTenant ran after SubstituteBindings, so route-model binding for '.
            'tenant-scoped models resolves without a tenant. Register IdentifyTenant '.
            'before SubstituteBindings in your middleware priority (bootstrap/app.php).'
        );

        $custom = $this->tenancyManager->customResolver();

        $tenant = $custom instanceof Closure
            ? $custom($request)
            : $this->tenantResolver->resolve($request);

        if ($tenant === null) {
            event(new TenantResolutionFailed($request));

            return $next($request);
        }

        $this->tenancyManager->initialize($tenant);

        $parameter = config('tenancy.route_parameter', 'tenant');
        $parameter = \is_string($parameter) ? $parameter : 'tenant';

        $route = $request->route();

        if ($route instanceof Route && $route->hasParameter($parameter)) {
            $route->setParameter($parameter, $tenant);
        }

        return $next($request);
    }

    public function terminate(): void
    {
        $this->tenancyManager->end();
    }

    private function bindingsAlreadySubstituted(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof Model
                && \in_array(BelongsToTenant::class, class_uses_recursive($parameter), strict: true)) {
                return true;
            }
        }

        return false;
    }
}
