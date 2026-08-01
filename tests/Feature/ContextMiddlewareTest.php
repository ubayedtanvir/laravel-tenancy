<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use UbayedTanvir\LaravelTenancy\Events\TenantContextChanged;
use UbayedTanvir\LaravelTenancy\Http\Middleware\EnsureTenantMember;
use UbayedTanvir\LaravelTenancy\Http\Middleware\IdentifyTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RecordsCurrentTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RedirectToCurrentTenant;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RequireExplicitSwitch;
use UbayedTanvir\LaravelTenancy\Http\Middleware\RequireTenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\User;

it('records the preference and announces the change on a safe request', function (): void {
    Event::fake([TenantContextChanged::class]);

    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach([$tenant->getKey(), $b->getKey()]);
    $user->switchTo($tenant);

    Route::middleware([IdentifyTenant::class, RequireTenant::class, EnsureTenantMember::class, RecordsCurrentTenant::class])
        ->get('/{tenant}/dash', fn (): string => 'ok');

    $this->actingAs($user)->get('/b/dash')->assertOk();

    Event::assertDispatched(TenantContextChanged::class);
    expect($user->fresh()?->currentTenantIs($b))->toBeTrue();
});

it('does not record the preference on an unsafe request', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach([$tenant->getKey(), $b->getKey()]);
    $user->switchTo($tenant);

    Route::middleware([IdentifyTenant::class, RequireTenant::class, EnsureTenantMember::class, RecordsCurrentTenant::class])
        ->post('/{tenant}/act', fn (): string => 'ok');

    $this->actingAs($user)->post('/b/act')->assertOk();

    expect($user->fresh()?->currentTenantIs($tenant))->toBeTrue();   // unchanged
});

it('redirects a tenant-less request to the landing tenant', function (): void {
    config(['tenancy.landing_route' => 'tenant.dash']);

    $tenant = Tenant::query()->create(['slug' => 'a']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach($tenant);
    $user->switchTo($tenant);

    Route::middleware([IdentifyTenant::class, RequireTenant::class])
        ->get('/{tenant}/dash', fn (): string => 'ok')->name('tenant.dash');
    Route::middleware([RedirectToCurrentTenant::class])
        ->get('/home', fn (): string => 'home');

    $this->actingAs($user)->get('/home')->assertRedirect('/a/dash');
});

it('redirects to confirmation when the tenant differs from the preference', function (): void {
    config(['tenancy.switch_confirmation_route' => 'tenant.confirm']);

    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach([$tenant->getKey(), $b->getKey()]);
    $user->switchTo($tenant);

    Route::get('/{tenant}/confirm', fn (): string => 'confirm')->name('tenant.confirm');
    Route::middleware(['web', IdentifyTenant::class, RequireTenant::class, RequireExplicitSwitch::class])
        ->get('/{tenant}/x', fn (): string => 'x');

    $this->actingAs($user)->get('/b/x')
        ->assertRedirect('/b/confirm')
        ->assertSessionHas('tenancy.intended');
});

it('reports first-tenant and external-navigation on the event', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $from = Tenant::query()->create(['slug' => 'b']);
    $user = User::query()->create(['name' => 'U']);

    $external = new TenantContextChanged($user, from: null, to: $tenant, request: Request::create('http://app.test/x'));

    $internalRequest = Request::create('http://app.test/x');
    $internalRequest->headers->set('referer', 'http://app.test/prev');

    $internal = new TenantContextChanged($user, $from, $tenant, $internalRequest);

    expect($external->isFirstTenant())->toBeTrue()
        ->and($external->isExternalNavigation())->toBeTrue()
        ->and($internal->isFirstTenant())->toBeFalse()
        ->and($internal->isExternalNavigation())->toBeFalse();
});
