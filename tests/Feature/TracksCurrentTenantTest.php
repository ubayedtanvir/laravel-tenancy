<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\User;

it('records and reads the landing preference', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $user = User::query()->create(['name' => 'U']);

    expect($user->currentTenantIs($tenant))->toBeFalse();

    $user->switchTo($tenant);

    expect($user->currentTenantIs($tenant))->toBeTrue()
        ->and($user->fresh()?->getAttribute('current_tenant_id'))->toBe($tenant->getKey());

    $user->forgetCurrentTenant();

    expect($user->fresh()?->getAttribute('current_tenant_id'))->toBeNull();
});

it('does not fire model events when recording the preference', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $user = User::query()->create(['name' => 'U']);

    $fired = false;
    User::updated(function () use (&$fired): void {
        $fired = true;
    });

    $user->switchTo($tenant);

    expect($fired)->toBeFalse();
});

it('lands on the stored tenant when still a member', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach($tenant);
    $user->switchTo($tenant);

    expect($user->resolveLandingTenant()?->getKey())->toBe($tenant->getKey());
});

it('self-heals when the stored tenant was deleted', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach([$tenant->getKey(), $b->getKey()]);
    $user->switchTo($tenant);

    $tenant->delete();   // nullOnDelete nulls the preference; cascade drops membership

    $landing = $user->resolveLandingTenant();

    expect($landing?->getKey())->toBe($b->getKey())
        ->and($user->fresh()?->getAttribute('current_tenant_id'))->toBe($b->getKey());
});

it('self-heals when membership was revoked', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);
    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach([$tenant->getKey(), $b->getKey()]);
    $user->switchTo($tenant);

    $user->tenants()->detach($tenant);

    expect($user->resolveLandingTenant()?->getKey())->toBe($b->getKey());
});

it('returns null and forgets the preference with no accessible tenant', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $user = User::query()->create(['name' => 'U']);

    $user->switchTo($tenant);   // stored, but never a member

    expect($user->resolveLandingTenant())->toBeNull()
        ->and($user->fresh()?->getAttribute('current_tenant_id'))->toBeNull();
});
