<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Events\TenantContextChanged;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Update the user's landing preference and dispatch a context-change event.
 *
 * Must run after membership verification, otherwise an unauthorized request
 * could overwrite the user's stored tenant preference.
 */
final readonly class RecordsCurrentTenant
{
    public function __construct(private TenancyManager $tenancyManager) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $this->tenancyManager->current();

        // Only record on safe (read) requests. A POST into a new tenant context
        // may warrant explicit acknowledgment rather than a silent preference update.
        if (! $user instanceof Model
            || ! $tenant instanceof IsTenant
            || ! $request->isMethodSafe()
            || ! method_exists($user, 'currentTenantIs')
            || ! method_exists($user, 'currentTenant')
            || $user->currentTenantIs($tenant) === true
        ) {
            return $next($request);
        }

        $relation = $user->currentTenant();
        $previous = $relation instanceof BelongsTo ? $relation->first() : null;

        // Synchronous so listeners can write to the session before the response is built.
        event(new TenantContextChanged(
            user: $user,
            from: $previous instanceof IsTenant ? $previous : null,
            to: $tenant,
            request: $request,
        ));

        // Deferred so the preference write does not block the response.
        defer(function () use ($user, $tenant): void {
            if (method_exists($user, 'switchTo')) {
                $user->switchTo($tenant);
            }
        });

        return $next($request);
    }
}
