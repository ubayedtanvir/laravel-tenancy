<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Console\RebuildFixtureCommand;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

it('runs an artisan command in a specific tenant context', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);

    Artisan::command('test:whoami', function (): void {
        Cache::put('whoami', tenant_id());
    });

    $this->artisan('tenancy:run', ['task' => 'test:whoami', '--tenant' => ['a']])
        ->assertSuccessful();

    expect(Cache::get('whoami'))->toBe($tenant->getKey());
});

it('runs an artisan command for every tenant', function (): void {
    Tenant::query()->create(['slug' => 'a']);
    Tenant::query()->create(['slug' => 'b']);

    Artisan::command('test:tick', function (): void {
        Cache::increment('ticks');
    });

    $this->artisan('tenancy:run', ['task' => 'test:tick', '--all' => true])
        ->assertSuccessful();

    expect(Cache::get('ticks'))->toBe(2);
});

it('fails when a named tenant does not exist', function (): void {
    Artisan::command('test:noop', fn (): int => 0);

    $this->artisan('tenancy:run', ['task' => 'test:noop', '--tenant' => ['ghost']])
        ->assertFailed();
});

it('runs a command using InteractsWithTenants for every tenant', function (): void {
    Tenant::query()->create(['slug' => 'a']);
    Tenant::query()->create(['slug' => 'b']);

    resolve(Kernel::class)->registerCommand(new RebuildFixtureCommand);

    $this->artisan('test:rebuild', ['--all' => true])->assertSuccessful();

    expect(Cache::get('rebuild_ticks'))->toBe(2);
});

it('prefixes tenant cache keys and leaves the global cache untouched', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    Tenancy::initialize($tenant);

    Cache::tenant()->put('kpi', 42);

    expect(Cache::tenant()->get('kpi'))->toBe(42)
        ->and(Cache::get('kpi'))->toBeNull()
        ->and(Cache::get('tenant:'.$tenant->getKey().':kpi'))->toBe(42);
});

it('throws when using the tenant cache with no tenant bound', function (): void {
    expect(fn () => Cache::tenant())->toThrow(TenantContextMissing::class);
});
