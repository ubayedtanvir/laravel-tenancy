<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Testing\InteractsWithTenancy;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

uses(InteractsWithTenancy::class);

it('proves a scoped model is isolated between tenants', function (): void {
    $this->assertTenantIsolated(
        Post::class,
        Tenant::query()->create(['slug' => 'a']),
        Tenant::query()->create(['slug' => 'b']),
    );
});

it('binds a tenant with actingAsTenant', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);

    $this->actingAsTenant($tenant);

    expect(tenant_id())->toBe($tenant->getKey());
});
