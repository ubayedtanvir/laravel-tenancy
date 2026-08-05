---
name: laravel-tenancy-development
description: "Develops single-database multi-tenant Laravel applications with ubayedtanvir/laravel-tenancy. Activates when adding the IsTenant interface or BelongsToTenant trait to models; writing migrations with $table->tenant() or $table->currentTenant() macros; configuring tenant middleware (IdentifyTenant, RequireTenant, EnsureTenantMember); setting up tenant resolvers (path, header, chain, or custom); writing cross-tenant queries with crossTenant(), withoutTenancy(), acrossTenants(), or forTenant(); working with tenant-scoped cache via Cache::tenant(); dispatching tenant-aware queue jobs; running artisan commands in tenant context with tenancy:run or InteractsWithTenants; configuring tenant resolution, strict mode, or landing routes; or when the user mentions tenancy, multi-tenancy, tenant isolation, tenant scoping, or row-level tenancy in a Laravel project."
license: MIT
metadata:
  author: ubayedtanvir
---

# Laravel Tenancy Development

## When to use this skill

Use when working with `ubayedtanvir/laravel-tenancy` — adding tenancy to models, writing migrations with tenant columns, configuring tenant middleware, setting up resolvers, writing cross-tenant queries, or working with tenant-aware queues, cache, or console commands.

## Tenant Model Setup

Any Eloquent model can be a tenant. Implement the marker interface — no methods required:

```php
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

final class Team extends Model implements IsTenant
{
    use HasUuids; // or HasUlids, or plain auto-increment
}
```

Set it in `config/tenancy.php`:

```php
'tenant' => [
    'model' => App\Models\Team::class,
],
```

## Scoping Models

Add `BelongsToTenant` to any model that belongs to a tenant:

```php
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;

final class Invoice extends Model
{
    use BelongsToTenant;
}
```

This automatically:
- Adds a global scope filtering queries by the current tenant
- Stamps the tenant column on create
- Rejects writes to the wrong tenant (`CrossTenantWriteDenied`)
- Makes the tenant column immutable after creation (`TenantOwnershipImmutable`)
- Guards the tenant column from mass assignment

For legacy tables with non-standard column names:

```php
final class LegacyOrder extends Model
{
    use BelongsToTenant;

    protected string $tenantForeignKey = 'account_id';
}
```

## Migrations

Use the `$table->tenant()` macro. It reads the tenant model and picks the right column type:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->tenant();   // foreign key + index + cascadeOnDelete
    $table->string('number');
    $table->timestamps();

    // Unique indexes must include the tenant column:
    $table->unique(['team_id', 'number']);
});
```

Custom column name: `$table->tenant('account_id')`.

Column only (no FK/index): `$table->tenantKey()`.

For the landing-preference column on users: `$table->currentTenant()` (nullable, nullOnDelete).

## Middleware

Register a middleware group in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('tenant', [
        \UbayedTanvir\LaravelTenancy\Http\Middleware\IdentifyTenant::class,
        \UbayedTanvir\LaravelTenancy\Http\Middleware\RequireTenant::class,
        \UbayedTanvir\LaravelTenancy\Http\Middleware\EnsureTenantMember::class,
    ]);
})
```

Apply to routes:

```php
Route::middleware(['web', 'auth', 'tenant'])
    ->prefix('{tenant}')
    ->as('tenant.')
    ->group(base_path('routes/tenant.php'));
```

Available aliases: `tenant`, `tenant.required`, `tenant.member`, `tenant.record`, `tenant.landing`, `tenant.strict-switch`.

## Tenant Resolution

Three built-in resolvers:

```php
// config/tenancy.php
'resolver' => PathTenantResolver::class,       // /acme/dashboard (default)
'resolver' => HeaderTenantResolver::class,     // X-Tenant: acme
'resolver' => [                                // chain — first match wins
    HeaderTenantResolver::class,
    PathTenantResolver::class,
],
```

Custom resolution in a service provider:

```php
Tenancy::resolveUsing(fn (Request $r) => Team::where('api_key', $r->bearerToken())->first());
```

## Cross-Tenant Queries

Always explicit. Every call dispatches `CrossTenantAccessGranted`:

```php
Tenancy::crossTenant(fn () => Invoice::count());

Invoice::query()->forTenant($otherTeam)->get();

Invoice::query()->acrossTenants()->count();

Invoice::query()->withoutTenancy()->where('status', 'overdue')->get();
```

## Facade API

```php
tenant()                             // ?IsTenant — global helper
tenant_id()                          // int|string|null — global helper
Tenancy::current()                   // ?IsTenant
Tenancy::currentOrFail()             // IsTenant or throws
Tenancy::initialized()               // bool
Tenancy::is($tenantOrId)             // bool
Tenancy::initialize($tenant)         // void
Tenancy::end()                       // void
Tenancy::runFor($tenant, fn () => …) // runs callback, restores previous
Tenancy::each(fn ($t) => …)          // all tenants, chunked
Tenancy::crossTenant(fn () => …)     // suspends scope
```

## Queues

Jobs dispatched in a tenant context automatically restore that context on the worker. Covers jobs, closures, mailables, notifications, chains, and batches.

To opt a job out:

```php
use UbayedTanvir\LaravelTenancy\Contracts\NotTenantAware;

final class SendPlatformDigest implements ShouldQueue, NotTenantAware {}
```

## Cache

`Cache::tenant()` returns a scoped decorator. Keys are prefixed with the tenant id:

```php
Cache::tenant()->remember('kpis', 300, fn () => $this->computeKpis());
Cache::get('feature-flags');   // still global
```

## Console Commands

Run any artisan command in tenant context:

```bash
php artisan tenancy:run "cache:clear" --tenant=acme
php artisan tenancy:run "cache:clear" --all
```

For custom commands, use `InteractsWithTenants`:

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

The trait adds `--tenant`, `--all`, and `--continue-on-error` options automatically.

## Team Switcher / Landing

For tracking which tenant a user last visited:

```php
final class User extends Authenticatable implements TenantMembership
{
    use HasTenants;
    use TracksCurrentTenant;
}
```

Migration: `$table->currentTenant()`.

Middleware: add `tenant.record` to record visits, `tenant.landing` to redirect to last tenant.

## Configuration

All config is in `config/tenancy.php`. Only `tenant.model` is required. Everything else derives from Laravel conventions:

- `tenant.foreign_key` — null means use the model's `getForeignKey()`
- `tenant.key_type` — null means infer from `HasUuids`/`HasUlids`/model metadata
- `strict` — `true` by default (queries without tenant throw)
- `resolver` — `PathTenantResolver::class` by default
- `cache.prefix` — `'tenant'` by default
- `queue.tenant_aware` — `true` by default
