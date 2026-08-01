<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\TenancyManager;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Queues\CountPostsJob;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Queues\PlatformJob;

beforeEach(function (): void {
    config([
        'queue.default' => 'database',
        'queue.connections.database' => [
            'driver' => 'database',
            'connection' => 'testing',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],
        'queue.failed' => [
            'driver' => 'database-uuids',
            'database' => 'testing',
            'table' => 'failed_jobs',
        ],
    ]);
});

it('does not leak the tenant across a real worker cycle', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::runFor($b, fn () => Post::query()->create(['title' => 'b1']));
    Tenancy::runFor($tenant, fn () => Bus::dispatch(new CountPostsJob));
    Tenancy::end();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    // The job was dispatched under A, which has no posts.
    expect(Cache::get('post_count'))->toBe(0)
        ->and(resolve(TenancyManager::class)->initialized())->toBeFalse();
});

it('does not bind a tenant for a NotTenantAware job', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);

    Tenancy::runFor($tenant, fn () => Bus::dispatch(new PlatformJob));
    Tenancy::end();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(Cache::get('platform_bound'))->toBeFalse();
});

it('fails a job whose tenant was deleted between dispatch and execution', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);

    Tenancy::runFor($tenant, fn () => Bus::dispatch(new CountPostsJob));
    Tenancy::end();

    $tenant->delete();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true, '--tries' => 1]);

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

it('does not stamp the payload when tenant-aware queueing is disabled', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    Tenancy::initialize($tenant);
    Tenancy::shouldQueueBeTenantAware(value: false);

    Bus::dispatch(new CountPostsJob);

    $raw = DB::table('jobs')->value('payload');
    $payload = is_string($raw) ? json_decode($raw, associative: true) : [];

    expect($payload)->not->toHaveKey('tenant_id');
});
