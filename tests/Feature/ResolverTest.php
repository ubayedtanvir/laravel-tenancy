<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\Resolvers\ChainTenantResolver;
use UbayedTanvir\LaravelTenancy\Resolvers\HeaderTenantResolver;
use UbayedTanvir\LaravelTenancy\Resolvers\PathTenantResolver;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

function pathRequest(string $uri, ?string $tenantKey): Request
{
    $request = Request::create($uri);
    $route = new Route('GET', '{tenant}/rest', fn (): null => null)->bind($request);

    $route->setParameter('tenant', $tenantKey);

    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('resolves a tenant from the route parameter', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    $resolver = new PathTenantResolver(resolve(TenantRepository::class));

    expect($resolver->resolve(pathRequest('/acme/rest', 'acme'))?->getKey())
        ->toBe($tenant->getKey());
});

it('resolves null for an unknown route parameter', function (): void {
    $resolver = new PathTenantResolver(resolve(TenantRepository::class));

    expect($resolver->resolve(pathRequest('/ghost/rest', 'ghost')))->toBeNull()
        ->and($resolver->resolve(pathRequest('/rest', tenantKey: null)))->toBeNull();
});

it('resolves a tenant from a header', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    $resolver = new HeaderTenantResolver(resolve(TenantRepository::class));

    $request = Request::create('/x');
    $request->headers->set('X-Tenant', 'acme');

    expect($resolver->resolve($request)?->getKey())->toBe($tenant->getKey())
        ->and($resolver->resolve(Request::create('/x')))->toBeNull();
});

it('registers a custom resolver via `resolveUsing()`', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'custom']);

    Tenancy::resolveUsing(fn (Request $request): ?IsTenant => Tenant::query()
        ->where('slug', $request->header('X-Custom-Tenant'))
        ->first());

    $resolver = Tenancy::customResolver();

    expect($resolver)->toBeInstanceOf(Closure::class);

    $request = Request::create('/anything');
    $request->headers->set('X-Custom-Tenant', 'custom');

    expect($resolver($request)?->getKey())->toBe($tenant->getKey());
});

it('returns null from the custom resolver when no tenant is found', function (): void {
    Tenancy::resolveUsing(fn (Request $request): ?IsTenant => Tenant::query()
        ->where('slug', $request->header('X-Custom-Tenant'))
        ->first());

    $resolver = Tenancy::customResolver();

    expect($resolver(Request::create('/x')))->toBeNull();
});

it('has no custom resolver by default', function (): void {
    expect(Tenancy::customResolver())->toBeNull();
});

it('returns the first non-null match in a chain', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);

    $null = new class implements TenantResolver
    {
        public function resolve(Request $request): ?IsTenant
        {
            return null;
        }
    };

    $chain = new ChainTenantResolver([
        $null,
        new HeaderTenantResolver(resolve(TenantRepository::class)),
    ]);

    $request = Request::create('/x');
    $request->headers->set('X-Tenant', 'acme');

    expect($chain->resolve($request)?->getKey())->toBe($tenant->getKey())
        ->and($chain->resolve(Request::create('/x')))->toBeNull();
});
