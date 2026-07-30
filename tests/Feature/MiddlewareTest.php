<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\Http\Middleware\EnsureTenantMember;
use UbayedTanvir\LaravelTenancy\Http\Middleware\IdentifyTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RequireTenant;
use UbayedTanvir\LaravelTenancy\TenancyManager;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\User;

it('identifies the tenant from the path and binds it', function (): void {
    Tenant::query()->create(['slug' => 'acme']);

    Route::middleware([IdentifyTenant::class, RequireTenant::class])
        ->get('/{tenant}/ping', fn () => Tenancy::current()?->getRouteKey());

    $this->get('/acme/ping')
        ->assertOk()
        ->assertSee('acme');
});

it('binds the tenant so scoped models resolve inside the route', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    Tenancy::runFor($tenant, fn () => Post::query()->create(['title' => 'a1']));

    Route::middleware([IdentifyTenant::class, RequireTenant::class])
        ->get('/{tenant}/posts', fn (): string => (string) Post::query()->count());

    $this->get('/acme/posts')
        ->assertOk()
        ->assertSee('1');
});

it('404s when the tenant cannot be resolved', function (): void {
    Route::middleware([IdentifyTenant::class, RequireTenant::class])
        ->get('/plain/ping', fn (): string => 'ok');

    $this->get('/plain/ping')
        ->assertNotFound();
});

it('lets a member through', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    Tenancy::initialize($tenant);

    $user = User::query()->create(['name' => 'U']);
    $user->tenants()->attach($tenant);

    $request = Request::create('/x');
    $request->setUserResolver(fn () => $user);

    $response = new EnsureTenantMember(resolve(TenancyManager::class))
        ->handle($request, fn (): Response => new Response('ok'));

    expect($response->getContent())
        ->toBe('ok');
});

it('404s a non-member', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    Tenancy::initialize($tenant);

    $user = User::query()->create(['name' => 'S']);

    $request = Request::create('/x');
    $request->setUserResolver(fn () => $user);

    expect(fn (): Response => new EnsureTenantMember(resolve(TenancyManager::class))
        ->handle($request, fn (): Response => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

it('404s a guest', function (): void {
    Tenancy::initialize(Tenant::query()->create(['slug' => 'acme']));

    expect(fn (): Response => new EnsureTenantMember(resolve(TenancyManager::class))
        ->handle(Request::create('/x'), fn (): Response => new Response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

it('treats the tenant-as-user as a member without a pivot', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'solo']);   // Tenant implements IsTenant
    Tenancy::initialize($tenant);

    $request = Request::create('/x');
    $request->setUserResolver(fn () => $tenant);    // the user IS the tenant

    $response = new EnsureTenantMember(resolve(TenancyManager::class))
        ->handle($request, fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});
