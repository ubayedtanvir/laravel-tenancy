<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantMembership;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Verify the authenticated user belongs to the current tenant.
 */
final readonly class EnsureTenantMember
{
    public function __construct(private TenancyManager $tenancyManager) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isTenant = $this->tenancyManager->currentOrFail();

        // The tenant IS the user in single-user-per-tenant applications
        if ($user instanceof IsTenant && $this->tenancyManager->is($user)) {
            return $next($request);
        }

        if ($user instanceof TenantMembership && $user->belongsToTenant($isTenant)) {
            return $next($request);
        }

        // 404, not 403. A 403 confirms the tenant exists.
        throw new NotFoundHttpException;
    }
}
