<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Redirect to a confirmation screen when the request tenant differs from the
 * user's stored preference.
 *
 * This middleware adds deliberate friction before a context switch and is
 * appropriate only when tenants represent hard security or legal boundaries.
 * It is not registered by default.
 */
final readonly class RequireExplicitSwitch
{
    public function __construct(private TenancyManager $tenancyManager) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isTenant = $this->tenancyManager->currentOrFail();

        if (! $user instanceof Model
            || ! method_exists($user, 'currentTenantIs')
            || $user->currentTenantIs($isTenant) === true
        ) {
            return $next($request);
        }

        // Preserve the intended URL so the confirmation screen can complete the navigation.
        $request->session()->put('tenancy.intended', $request->fullUrl());

        $route = config('tenancy.switch_confirmation_route', 'tenant.switch.confirm');
        $parameter = config('tenancy.route_parameter', 'tenant');

        return to_route(\is_string($route)
            ? $route
            : 'tenant.switch.confirm', [
                (\is_string($parameter) ? $parameter : 'tenant') => $isTenant->getRouteKey(),
            ]);
    }
}
