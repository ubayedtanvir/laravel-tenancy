<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Exceptions\CrossTenantWriteDenied;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantOwnershipImmutable;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

it('stamps the current tenant on create', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);

    Tenancy::initialize($tenant);

    $post = Post::query()->create(['title' => 'x']);

    expect($post->tenant_id)->toBe($tenant->getKey());
});

it('guards the tenant key against mass assignment', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::initialize($tenant);

    // A foreign tenant_id in the mass-assigned attributes is ignored, and the
    // current tenant is stamped instead — the request body cannot choose it.
    $post = Post::query()->create(['title' => 'x', 'tenant_id' => $b->getKey()]);

    expect($post->tenant_id)->toBe($tenant->getKey());
});

it('denies a create explicitly assigned to another tenant', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::initialize($tenant);

    $post = new Post(['title' => 'x']);
    $post->tenant_id = $b->getKey();

    expect(fn () => $post->save())->toThrow(CrossTenantWriteDenied::class);
});

it('treats string and int tenant keys as equal on create', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);

    Tenancy::initialize($tenant);

    $post = new Post(['title' => 'x']);
    $post->tenant_id = (string) $tenant->getKey();
    $post->save();

    expect($post->exists)->toBeTrue()
        ->and($post->tenant_id)->toBe((string) $tenant->getKey());
});

it('seals the tenant key against later changes', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::initialize($tenant);

    $post = Post::query()->create(['title' => 'x']);
    $post->tenant_id = $b->getKey();

    expect(fn () => $post->save())->toThrow(TenantOwnershipImmutable::class);
});

it('requires an explicit tenant key when creating inside `crossTenant()`', function (): void {
    Tenant::query()->create(['slug' => 'a']);

    Tenancy::crossTenant(function (): void {
        expect(fn () => Post::query()->create(['title' => 'x']))
            ->toThrow(TenantContextMissing::class);
    });
});

it('allows a hand-stamped create inside `crossTenant()`', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'b']);

    Tenancy::crossTenant(function () use ($tenant): void {
        $post = new Post(['title' => 'x']);
        $post->tenant_id = $tenant->getKey();
        $post->save();

        expect($post->exists)->toBeTrue();
    });
});

it('allows changing the tenant key inside `crossTenant()`', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    $post = Tenancy::runFor($tenant, fn () => Post::query()->create(['title' => 'x']));

    Tenancy::crossTenant(function () use ($post, $b): void {
        $post->tenant_id = $b->getKey();
        $post->save();
    });

    $moved = Post::query()->withoutTenancy()->find($post->getKey());

    expect($moved?->tenant_id)->toBe($b->getKey());
});
