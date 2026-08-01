<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

/**
 * Redirect an authenticated user to their last-active tenant.
 */
final readonly class RedirectToCurrentTenant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Model || ! method_exists($user, 'resolveLandingTenant')) {
            return $next($request);
        }

        $tenant = $user->resolveLandingTenant();

        if (! $tenant instanceof IsTenant) {
            return $next($request);
        }

        $route = config('tenancy.landing_route', 'tenant.dashboard');
        $parameter = config('tenancy.route_parameter', 'tenant');

        return to_route(\is_string($route)
            ? $route
            : 'tenant.dashboard', [
                (\is_string($parameter) ? $parameter : 'tenant') => $tenant->getRouteKey(),
            ]);
    }
}
