<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use UbayedTanvir\LaravelTenancy\Database\LenientModeWarning;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Comment;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\LegacyPost;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

it('filters reads to the current tenant', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::runFor($tenant, fn () => Post::query()->create(['title' => 'a1']));
    Tenancy::runFor($b, fn () => Post::query()->create(['title' => 'b1']));

    Tenancy::initialize($tenant);

    expect(Post::query()->count())->toBe(1)
        ->and(Post::query()->first()?->title)->toBe('a1');
});

it('throws when querying with no tenant bound in strict mode', function (): void {
    expect(fn () => Post::query()->count())->toThrow(TenantContextMissing::class);
});

it('does not throw and warns once per model in lenient mode', function (): void {
    config(['tenancy.strict' => false]);
    LenientModeWarning::flush();
    Log::spy();

    $tenant = Tenant::query()->create(['slug' => 'a']);
    Tenancy::runFor($tenant, fn () => Post::query()->create(['title' => 'a1']));

    // No tenant bound: lenient mode leaves the query unscoped.
    expect(Post::query()->count())->toBe(1);
    Post::query()->count();   // second query, same model

    Log::shouldHaveReceived('warning')->once();
});

it('suspends the read scope inside `crossTenant()`', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::runFor($tenant, fn () => Post::query()->create(['title' => 'a1']));
    Tenancy::runFor($b, fn () => Post::query()->create(['title' => 'b1']));

    Tenancy::initialize($tenant);

    expect(Tenancy::crossTenant(fn () => Post::query()->count()))->toBe(2);
});

it('provides explicit scope escape hatches', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::runFor($tenant, fn () => Post::query()->create(['title' => 'a1']));
    Tenancy::runFor($b, fn () => Post::query()->create(['title' => 'b1']));

    Tenancy::initialize($tenant);

    expect(Post::query()->withoutTenancy()->count())->toBe(2)
        ->and(Post::query()->acrossTenants()->count())->toBe(2)
        ->and(Post::query()->forTenant($b)->count())->toBe(1)
        ->and(Post::query()->forTenant($b)->first()?->title)->toBe('b1');
});

it('qualifies the tenant column so joins are unambiguous', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    Tenancy::initialize($tenant);

    $post = Post::query()->create(['title' => 'a1']);
    Comment::query()->create(['body' => 'c1', 'post_id' => $post->getKey()]);

    // Both tables carry tenant_id; without qualifyColumn this join raises
    // "ambiguous column name: tenant_id".
    $rows = Comment::query()
        ->join('posts', 'comments.post_id', '=', 'posts.id')
        ->get();

    expect($rows)->toHaveCount(1);
});

it('respects a per-model foreign key override', function (): void {
    $tenant = Tenant::query()->create(['slug' => 'a']);
    $b = Tenant::query()->create(['slug' => 'b']);

    Tenancy::runFor($tenant, fn () => LegacyPost::query()->create(['title' => 'a1']));
    Tenancy::runFor($b, fn () => LegacyPost::query()->create(['title' => 'b1']));

    Tenancy::initialize($tenant);

    expect(LegacyPost::query()->count())->toBe(1)
        ->and(LegacyPost::query()->first()?->account_id)->toBe($tenant->getKey())
        ->and(LegacyPost::query()->first()?->title)->toBe('a1');
});
