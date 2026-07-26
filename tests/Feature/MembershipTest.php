<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Tenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\User;

it('reports tenant membership through `HasTenants`', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    $user = User::query()->create(['name' => 'U']);

    $user->tenants()->attach($tenant);

    expect($user->belongsToTenant($tenant))->toBeTrue()
        ->and($user->belongsToTenant($b))->toBeFalse()
        ->and($user->tenants()->pluck('slug')->all())->toBe(['a']);
});
