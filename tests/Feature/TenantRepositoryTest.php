<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

it('resolves and caches the tenant key', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    $tenantRepository = resolve(TenantRepository::class);

    expect($tenantRepository->findByRouteKey('acme')?->getKey())->toBe($tenant->getKey())
        ->and(Cache::get('tenancy:key:acme'))->toBe($tenant->getKey());
});

it('negatively caches a miss', function (): void {
    $tenantRepository = resolve(TenantRepository::class);

    expect($tenantRepository->findByRouteKey('ghost'))->toBeNull()
        ->and(Cache::get('tenancy:key:ghost'))->toBeFalse();
});

it('forgets a cached key', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    $tenantRepository = resolve(TenantRepository::class);

    $tenantRepository->findByRouteKey('acme');
    $tenantRepository->forget($tenant);

    expect(Cache::has('tenancy:key:acme'))->toBeFalse();
});

it('invalidates both keys when a tenant is renamed', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'old']);
    $tenantRepository = resolve(TenantRepository::class);

    // Warm the positive cache for the old key and the negative cache for the new.
    $tenantRepository->findByRouteKey('old');
    $tenantRepository->findByRouteKey('new');

    $tenant->update(['slug' => 'new']);

    expect($tenantRepository->findByRouteKey('old'))->toBeNull()
        ->and($tenantRepository->findByRouteKey('new')?->getKey())->toBe($tenant->getKey());
});

it('invalidates the cache when a tenant is deleted', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);
    $tenantRepository = resolve(TenantRepository::class);

    $tenantRepository->findByRouteKey('acme');

    $tenant->delete();

    expect(Cache::has('tenancy:key:acme'))->toBeFalse()
        ->and($tenantRepository->findByRouteKey('acme'))->toBeNull();
});
