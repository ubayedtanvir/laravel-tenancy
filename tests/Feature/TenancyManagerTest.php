<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use UbayedTanvir\LaravelTenancy\Events\CrossTenantAccessGranted;
use UbayedTanvir\LaravelTenancy\Events\TenancyEnded;
use UbayedTanvir\LaravelTenancy\Events\TenancyInitialized;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Tenant;

it('binds and reflects the current tenant', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);

    Tenancy::initialize($tenant);

    expect(Tenancy::check())->toBeTrue()
        ->and(Tenancy::current()?->getKey())->toBe($tenant->getKey())
        ->and(Tenancy::id())->toBe($tenant->getKey())
        ->and(Context::get('tenant_id'))->toBe($tenant->getKey());
});

it('clears context on end', function (): void {
    Tenancy::initialize(Tenant::query()->create(['slug' => 'acme']));
    Tenancy::end();

    expect(Tenancy::check())->toBeFalse()
        ->and(Tenancy::id())->toBeNull()
        ->and(Context::get('tenant_id'))->toBeNull();
});

it('throws on `currentOrFail()` / `idOrFail()` with no tenant', function (): void {
    expect(fn () => Tenancy::currentOrFail())->toThrow(TenantContextMissing::class);
    expect(fn () => Tenancy::idOrFail())->toThrow(TenantContextMissing::class);
});

it('matches the current tenant by instance or key', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::initialize($tenant);

    expect(Tenancy::is($tenant))->toBeTrue()
        ->and(Tenancy::is($tenant->getKey()))->toBeTrue()
        ->and(Tenancy::is((string) $tenant->getKey()))->toBeTrue()
        ->and(Tenancy::is($b))->toBeFalse();
});

it('`runFor()` restores the previous tenant', function (): void {
    $a = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::initialize($a);

    $result = Tenancy::runFor($b, fn (Tenant $tenant) => $tenant->getKey());

    expect($result)->toBe($b->getKey())
        ->and(Tenancy::id())->toBe($a->getKey());
});

it('`runFor()` restores the no-tenant state', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'b']);

    Tenancy::runFor($tenant, fn (): null => null);

    expect(Tenancy::check())->toBeFalse();
});

it('`runFor()` restores even when the callback throws', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'b']);

    try {
        Tenancy::runFor($tenant, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Tenancy::check())->toBeFalse();
});

it('`crossTenant()` suspends, nests, and restores', function (): void {
    $granted = 0;
    Event::listen(CrossTenantAccessGranted::class, function () use (&$granted): void {
        $granted++;
    });

    Tenancy::initialize(Tenant::query()->create(['slug' => 'a']));

    $depths = [];
    Tenancy::crossTenant(function () use (&$depths): void {
        $depths[] = Tenancy::crossTenantEnabled();
        Tenancy::crossTenant(function () use (&$depths): void {
            $depths[] = Tenancy::crossTenantEnabled();
        });
        $depths[] = Tenancy::crossTenantEnabled();
    });

    expect($depths)->toBe([true, true, true])
        ->and(Tenancy::crossTenantEnabled())->toBeFalse()
        ->and($granted)->toBe(2);
});

it('fires one `Initialized` and one `Ended` per transition', function (): void {
    $init = 0;
    $ended = 0;
    Event::listen(TenancyInitialized::class, function () use (&$init): void {
        $init++;
    });
    Event::listen(TenancyEnded::class, function () use (&$ended): void {
        $ended++;
    });

    $tenant = Tenant::query()->create(['slug' => 'a']);

    Tenancy::initialize($tenant);
    Tenancy::initialize($tenant);   // same tenant → no-op, no extra events
    Tenancy::end();

    expect($init)->toBe(1)->and($ended)->toBe(1);
});

it('switching tenants ends the previous once', function (): void {
    $ended = 0;
    Event::listen(TenancyEnded::class, function () use (&$ended): void {
        $ended++;
    });

    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::initialize($tenant);
    Tenancy::initialize($b);   // ends A, starts B

    expect($ended)->toBe(1)
        ->and(Tenancy::id())->toBe($b->getKey());
});

it('runs a callback for every tenant and ends after', function (): void {
    Tenant::query()->create(['slug' => 'a']);
    Tenant::query()->create(['slug' => 'b']);
    Tenant::query()->create(['slug' => 'c']);

    $seen = [];
    Tenancy::each(function (Tenant $tenant) use (&$seen): void {
        $seen[] = $tenant->slug;
    });

    sort($seen);

    expect($seen)->toBe(['a', 'b', 'c'])
        ->and(Tenancy::check())->toBeFalse();
});

it('derives the tenant foreign key from convention', function (): void {
    expect(Tenancy::foreignKey())->toBe('tenant_id');
});

it('exposes the current tenant through the helpers', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'acme']);

    expect(tenant())->toBeNull()
        ->and(tenant_id())->toBeNull();

    Tenancy::initialize($tenant);

    expect(tenant()->getKey())->toBe($tenant->getKey())
        ->and(tenant_id())->toBe($tenant->getKey());
});
