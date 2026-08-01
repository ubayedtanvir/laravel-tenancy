<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Comment;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\LegacyPost;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    class_exists(Post::class);
    class_exists(Comment::class);
    class_exists(LegacyPost::class);

    config(['tenancy.audit.ignore_tables' => ['tenant_user']]);
});

it('passes a correctly isolated schema', function (): void {
    $this->artisan('tenancy:audit')->assertSuccessful();
});

it('flags a table with the tenant column but no trait', function (): void {
    Schema::create('audit_logs', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->foreignId('tenant_id');
        $blueprint->timestamps();
    });

    $this->artisan('tenancy:audit')
        ->expectsOutputToContain('audit_logs')
        ->assertFailed();
});

it('flags a non-composite unique index on a scoped table', function (): void {
    Schema::drop('posts');
    Schema::create('posts', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->tenant();
        $blueprint->string('number');
        $blueprint->unique('number');   // not composite with tenant_id
        $blueprint->timestamps();
    });

    $this->artisan('tenancy:audit')->assertFailed();
});

it('flags a scoped table missing its tenant index', function (): void {
    Schema::drop('posts');
    Schema::create('posts', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->tenantKey();        // column only — no index, no foreign key
        $blueprint->string('title')->nullable();
        $blueprint->timestamps();
    });

    $this->artisan('tenancy:audit', ['--fail-on' => 'warn'])->assertFailed();
});

it('fails when strict mode is disabled and --fail-on=warn', function (): void {
    config(['tenancy.strict' => false]);

    $this->artisan('tenancy:audit', ['--fail-on' => 'warn'])->assertFailed();
});

it('emits machine-readable JSON', function (): void {
    Schema::create('audit_logs', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->foreignId('tenant_id');
        $blueprint->timestamps();
    });

    $this->artisan('tenancy:audit', ['--json' => true])->assertFailed();
});
