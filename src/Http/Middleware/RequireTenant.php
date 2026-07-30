<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Abort with a 404 if no tenant has been identified for the request.
 */
final readonly class RequireTenant
{
    public function __construct(private TenancyManager $tenancyManager) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        throw_unless($this->tenancyManager->initialized(), NotFoundHttpException::class);

        return $next($request);
    }
}
