<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/logo-horizontal-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="art/logo-horizontal.svg">
    <img src="art/logo-horizontal.svg" alt="Laravel Tenancy — Single-database multi-tenancy for Laravel" width="420">
  </picture>
</p>

<p align="center">
  <strong>Single-database multi-tenancy for Laravel that fails closed.</strong>
</p>

<p align="center">

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ubayedtanvir/laravel-tenancy.svg?style=flat-square)](https://packagist.org/packages/ubayedtanvir/laravel-tenancy)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ubayedtanvir/laravel-tenancy/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ubayedtanvir/laravel-tenancy/actions?query=workflow%3Atests+branch%3Amain)
[![PHPStan Level 10](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Total Downloads](https://img.shields.io/packagist/dt/ubayedtanvir/laravel-tenancy.svg?style=flat-square)](https://packagist.org/packages/ubayedtanvir/laravel-tenancy)
[![License](https://img.shields.io/packagist/l/ubayedtanvir/laravel-tenancy.svg?style=flat-square)](LICENSE)

</p>

---

Laravel Tenancy adds single-database multi-tenancy to Laravel the way you'd expect a first-party feature to work — implement a marker interface on your tenant model, add a trait to scoped models, and the package handles query filtering, write stamping, foreign keys, and middleware. It follows Laravel conventions so there's almost nothing to configure if your app already follows them.

Where it differs from existing packages: this one is built specifically for shared-schema tenancy, and it **fails closed**. A query without a tenant context throws an exception, a write to the wrong tenant is rejected, and the tenant column is immutable after creation. You catch the bug in your test suite, not in a customer's data.

```php
final class Team extends Model implements IsTenant {}

final class Invoice extends Model
{
    use BelongsToTenant;
}

// migration
$table->tenant();   // uuid, ulid, or bigint — figured out from your model
```

> [spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy) and [stancl/tenancy](https://github.com/archtechx/tenancy) are great if you need a database per tenant. This package does shared-schema only — one database, row-level isolation.

---

## Requirements

- PHP 8.4+
- Laravel 13

---

## Installation

```bash
composer require ubayedtanvir/laravel-tenancy
```

```bash
php artisan tenancy:install
```

The install command figures out your tenant model, foreign key, and column type, then tells you what it picked.

---

## Quick Start

### 1. Mark your tenant model

```php
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

final class Team extends Model implements IsTenant
{
    use HasUuids; // or HasUlids, or just auto-increment — doesn't matter
}
```

`IsTenant` is a marker interface. No methods to implement. Any model works — Team, User, Store, whatever your app calls a tenant.
Foreign keys follow Laravel's `getForeignKey()` convention. If you follow the convention, there's nothing to configure.

### 2. Scope your models

```php
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;

final class Invoice extends Model
{
    use BelongsToTenant;
}
```

This adds a global scope, auto-stamps the tenant on create, guards the column against mass assignment, throws on cross-tenant writes, and makes the tenant column immutable. It also gives you `withoutTenancy()`, `acrossTenants()`, and `forTenant($tenant)` scopes.

### 3. Migration

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->tenant();   // foreign key, index, cascade — all wired
    $table->string('number');
    $table->timestamps();

    $table->unique(['team_id', 'number']); // unique per tenant, not globally
});
```

`$table->tenant()` looks at your tenant model and picks the right column type.

### 4. Middleware

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('tenant', [
        \UbayedTanvir\LaravelTenancy\Http\Middleware\IdentifyTenant::class,
        \UbayedTanvir\LaravelTenancy\Http\Middleware\RequireTenant::class,
        \UbayedTanvir\LaravelTenancy\Http\Middleware\EnsureTenantMember::class,
    ]);
})
```

Or with the aliases the package registers: `['tenant', 'tenant.required', 'tenant.member']`.

### 5. Routes

```php
Route::middleware(['web', 'auth', 'tenant'])
    ->prefix('{tenant}')
    ->as('tenant.')
    ->group(base_path('routes/tenant.php'));
```

Now `/acme/invoices` queries acme's data and `/globex/invoices` queries globex's. No tenant context = exception.

## Tenant Resolution

Three resolvers ship out of the box:

```php
// config/tenancy.php

'resolver' => PathTenantResolver::class,           // /acme/dashboard (default)
// or
'resolver' => HeaderTenantResolver::class,         // X-Tenant: acme
// or
'resolver' => [                                    // chain — first match wins
    HeaderTenantResolver::class,
    PathTenantResolver::class,
],
```

For anything custom:

```php
Tenancy::resolveUsing(fn (Request $r) => Team::where('api_key', $r->bearerToken())->first());
```

---

## Cross-Tenant Queries

When you need to reach across tenants, you can — but it's always explicit:

```php
Tenancy::crossTenant(fn () => Invoice::count());

Invoice::query()->forTenant($otherTeam)->get();

Invoice::query()->acrossTenants()->count();
```

Every cross-tenant call dispatches a `CrossTenantAccessGranted` event, so you can log or audit it. And `grep -rn 'crossTenant\|acrossTenants\|withoutTenancy' app/` gives you a full list of every place isolation is relaxed.

---

## Queues

Jobs dispatched inside a tenant context run in that same context when the worker picks them up. This works for jobs, mailables, notifications, listeners, closures, chains, and batches — the tenant id goes into the queue payload via `Queue::createPayloadUsing`.

```php
SendInvoiceEmail::dispatch($invoice);     // runs as the current tenant
Mail::to($user)->queue(new InvoiceMail($invoice));
$user->notify(new InvoicePaid($invoice));
```

If a job shouldn't be tenant-aware, mark it:

```php
use UbayedTanvir\LaravelTenancy\Contracts\NotTenantAware;

final class SendPlatformDigest implements ShouldQueue, NotTenantAware {}
```

---

## Console Commands

Run any artisan command inside a tenant context:

```bash
php artisan tenancy:run "cache:clear" --tenant=acme
php artisan tenancy:run "cache:clear" --all
```

For your own commands, there's a trait:

```php
use UbayedTanvir\LaravelTenancy\Concerns\InteractsWithTenants;

#[Signature('reports:rebuild')]
final class RebuildReports extends Command
{
    use InteractsWithTenants;

    protected function handleForTenant(IsTenant $tenant): int
    {
        Report::query()->stale()->each->rebuild();

        return self::SUCCESS;
    }
}
```

```bash
php artisan reports:rebuild --tenant=acme
php artisan reports:rebuild --all --continue-on-error
```

The trait adds `--tenant`, `--all`, and `--continue-on-error` options automatically.

---

## Tenant-Scoped Cache

`Cache::tenant()` returns a decorator that prefixes keys with the current tenant. The global cache stays untouched:

```php
Cache::tenant()->remember('kpis', 300, fn () => $this->computeKpis());
Cache::get('feature-flags');   // still global
```

---

## Schema Auditor

```bash
php artisan tenancy:audit
```

```
  Tenant .................................. App\Models\Team (uuid)
  Foreign key ............................. team_id
  Strict mode ............................. enabled

  ✓  14 scoped models, all using BelongsToTenant
  ✓  14 tenant columns match the tenant key type
  ✓  all unique indexes are composite with team_id
  ✗  2 problems found

  ERROR  invoices.invoices_number_unique is unique on (number) without
         team_id. Tenant B cannot create a number tenant A already used.
         → $table->unique(['team_id', 'number']);

  ERROR  audit_logs has a team_id column but App\Models\AuditLog does
         not use BelongsToTenant. Queries return all tenants' rows.
         → use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;
```

Checks for missing traits, non-composite unique indexes, column type mismatches, missing foreign keys, and strict mode. You can run it in CI:

```yaml
php artisan tenancy:audit --fail-on=warn
```

---

## Testing

The package ships a `InteractsWithTenancy` trait with an `assertTenantIsolated` helper that checks read isolation, write rejection, ownership immutability, and the missing-context exception:

```php
use UbayedTanvir\LaravelTenancy\Testing\InteractsWithTenancy;

it('isolates invoices between tenants', function () {
    $this->assertTenantIsolated(
        Invoice::class,
        Team::factory()->create(),
        Team::factory()->create(),
    );
});
```

There's also `actingAsTenant($tenant)` for when you need to set up the context manually in your tests.

---

## Current Tenant Tracking

For team switchers and "redirect to last visited tenant" flows:

```php
final class User extends Authenticatable implements TenantMembership
{
    use HasTenants;
    use TracksCurrentTenant;
}
```

```php
// migration
$table->currentTenant();   // nullable, nullOnDelete
```

Add `RecordsCurrentTenant` to your middleware stack to keep the preference up to date on each GET request. Use the `tenant.landing` middleware on your landing route to redirect users to their last tenant (self-heals if that tenant was deleted or the user lost access).

```php
Route::middleware(['auth', 'tenant.landing'])
    ->get('/dashboard', fn () => abort(404))
    ->name('dashboard');
```

This preference is only used for landing — never for scoping or authorization. The URL is always authoritative.

---

## Custom Foreign Key

If a table uses a non-standard column name:

```php
final class LegacyOrder extends Model
{
    use BelongsToTenant;

    protected string $tenantForeignKey = 'account_id';
}
```

```php
$table->tenant('account_id');
```

---

## API Reference

```php
tenant()                             // ?IsTenant
tenant_id()                          // int|string|null
Tenancy::current()                   // ?IsTenant
Tenancy::currentOrFail()             // IsTenant or throws
Tenancy::initialized()               // bool
Tenancy::is($tenantOrId)             // bool

Tenancy::initialize($tenant)
Tenancy::end()
Tenancy::runFor($tenant, fn () => …) // runs callback, restores previous
Tenancy::each(fn ($t) => …)          // all tenants, chunked

Tenancy::crossTenant(fn () => …)     // suspends scope
Model::query()->withoutTenancy()
Model::query()->acrossTenants()
Model::query()->forTenant($tenant)

Cache::tenant()->get($key)
Cache::tenant()->put($key, $value)
Cache::tenant()->remember($key, $ttl, $callback)
```

## Middleware Aliases

| Alias | Class |
|---|---|
| `tenant` | `IdentifyTenant` |
| `tenant.required` | `RequireTenant` |
| `tenant.member` | `EnsureTenantMember` |
| `tenant.record` | `RecordsCurrentTenant` |
| `tenant.landing` | `RedirectToCurrentTenant` |
| `tenant.strict-switch` | `RequireExplicitSwitch` |

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover a security vulnerability, please email **ubayedtanvir@yahoo.com** instead of using the issue tracker.

Please see [SECURITY](SECURITY.md) for our full security policy.

## Credits

- [Ubayed Tanvir](https://github.com/ubayedtanvir)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
