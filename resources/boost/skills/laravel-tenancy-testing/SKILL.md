---
name: laravel-tenancy-testing
description: Test tenant isolation in Laravel applications using ubayedtanvir/laravel-tenancy. Use when writing tests that verify tenant scoping, cross-tenant write guards, context-missing exceptions, or when setting up tenant context in test cases.
---

# Laravel Tenancy Testing

## When to use this skill

Use when writing tests that verify tenant data isolation, set up tenant context for feature tests, or check that cross-tenant operations are properly guarded. The package ships a `InteractsWithTenancy` trait with assertion helpers.

## Setting Up Tenant Context

Use `actingAsTenant()` to bind a tenant for the duration of a test:

```php
use UbayedTanvir\LaravelTenancy\Testing\InteractsWithTenancy;

uses(InteractsWithTenancy::class);

it('creates an invoice for the current tenant', function () {
    $team = Team::factory()->create();

    $this->actingAsTenant($team);

    $invoice = Invoice::factory()->create();

    expect($invoice->team_id)->toBe($team->id);
});
```

This calls `Tenancy::initialize($tenant)` under the hood.

## Asserting Tenant Isolation

`assertTenantIsolated()` verifies four properties in one call:

1. **Read isolation** — tenant A cannot see tenant B's records
2. **Count isolation** — tenant A sees zero records from B
3. **Cross-tenant write rejection** — creating a record with B's key while acting as A throws `CrossTenantWriteDenied`
4. **Missing context exception** — querying without any tenant bound throws `TenantContextMissing`

```php
uses(InteractsWithTenancy::class);

it('isolates invoices between tenants', function () {
    $this->assertTenantIsolated(
        Invoice::class,
        Team::factory()->create(),
        Team::factory()->create(),
    );
});
```

The model class must have a factory. Both tenants must be distinct instances.

## Testing Cross-Tenant Access

Test that explicit cross-tenant calls work when they should:

```php
it('allows admin reporting across tenants', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    Tenancy::runFor($teamA, fn () => Invoice::factory()->count(3)->create());
    Tenancy::runFor($teamB, fn () => Invoice::factory()->count(2)->create());

    $total = Tenancy::crossTenant(fn () => Invoice::query()->count());

    expect($total)->toBe(5);
});
```

## Testing with Specific Tenant Context

Use `Tenancy::runFor()` to run code in a specific tenant's context with automatic cleanup:

```php
it('scopes cache keys per tenant', function () {
    $team = Team::factory()->create();

    Tenancy::runFor($team, function () {
        Cache::tenant()->put('key', 'value');

        expect(Cache::tenant()->get('key'))->toBe('value');
    });

    // Context is restored after runFor
    expect(Tenancy::initialized())->toBeFalse();
});
```

## Testing Tenant-Aware Jobs

Verify that jobs dispatched in a tenant context carry the tenant id:

```php
it('dispatches jobs with tenant context', function () {
    Queue::fake();

    $team = Team::factory()->create();

    $this->actingAsTenant($team);

    SendInvoiceEmail::dispatch($invoice);

    Queue::assertPushed(SendInvoiceEmail::class);
});
```

## Testing Middleware

For feature tests that hit tenant routes, include the tenant in the URL:

```php
it('requires tenant membership', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    // User is NOT a member of the team
    $this->actingAs($user)
        ->get("/{$team->slug}/dashboard")
        ->assertNotFound();

    // Attach the user, try again
    $user->tenants()->attach($team);

    $this->actingAs($user)
        ->get("/{$team->slug}/dashboard")
        ->assertOk();
});
```

## Testing Console Commands

Test tenant-scoped artisan commands:

```php
it('runs command for a specific tenant', function () {
    $team = Team::factory()->create();

    $this->artisan('reports:rebuild', ['--tenant' => [$team->slug]])
        ->assertSuccessful();
});

it('runs command for all tenants', function () {
    Team::factory()->count(3)->create();

    $this->artisan('reports:rebuild', ['--all' => true])
        ->assertSuccessful();
});
```

## Key Points

- Always use `actingAsTenant()` or `Tenancy::runFor()` to set context — never call `Tenancy::initialize()` directly in tests without cleanup.
- `assertTenantIsolated()` requires models with factories.
- Tenant context is NOT automatically cleaned up between tests. Use `actingAsTenant()` in each test or add `Tenancy::end()` in teardown.
- Strict mode is on by default. Tests that intentionally query without a tenant should catch `TenantContextMissing`.
