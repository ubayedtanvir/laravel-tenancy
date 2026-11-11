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

Add `RecordsCurrentTenant` to your middleware stack to keep the preference up to date on each GET request. Use the `tenant.landing` middleware on your landing route to redirect users to their last tenant (self-heals if that tenant was deleted or the user lost access). When a tenant is deleted, switch affected users to their next available tenant:

```php
Route::middleware(['auth', 'tenant.landing'])
    ->get('/dashboard', fn () => abort(404))
    ->name('dashboard');
```

```php
// When a tenant is deleted, reassign its members:
$team->members->each(fn ($user) => $user->switchToDefault());
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

$user->switchTo($tenant)             // record landing preference
$user->switchToDefault()             // switch to first accessible tenant
$user->forgetCurrentTenant()         // clear landing preference
$user->resolveLandingTenant()        // ?IsTenant — self-heals stale prefs

Cache::tenant()->get($key)
Cache::tenant()->put($key, $value)
Cache::tenant()->remember($key, $ttl, $callback)
```

## Middleware Aliases

| Alias | Class | Purpose |
|---|---|---|
| `tenant` | `IdentifyTenant` | Resolves and binds the tenant from the request |
| `tenant.required` | `RequireTenant` | Returns 404 when no tenant is bound |
| `tenant.member` | `EnsureTenantMember` | Returns 404 if the user isn't a member |
| `tenant.record` | `RecordsCurrentTenant` | Saves the tenant as the user's landing preference |
| `tenant.landing` | `RedirectToCurrentTenant` | Redirects to the user's last-visited tenant |
| `tenant.strict-switch` | `RequireExplicitSwitch` | Requires explicit confirmation to switch tenants |

---

## Common Pitfalls

These aren't enforced by the package, but each one comes up in real applications.

### Name nested route parameters carefully

The tenant route parameter (`tenancy.route_parameter`, `tenant` by default) is reserved. `IdentifyTenant` always replaces it with the resolved tenant. If a route needs a second instance of your tenant model, give that parameter its own name.

Laravel also scopes a nested route binding to the parameter before it when the nested parameter uses a custom key (`{team:id}`) or the route calls `scopeBindings()`. It finds the child through a relation guessed from the parameter name, so on the route below Laravel looks the team up through `$user->teams()`. If that relation doesn't exist, or means something else, the route returns 404.

Turn the scoping off for that route and authorize explicitly instead, because the scoping was acting as your ownership check:

```php
Route::get('/members/{user}/teams/{team:id}', ShowMemberTeam::class)
    ->withoutScopedBindings()
    ->can('view', 'team');
```

If the custom key is the model's route key anyway, `{team}` without `:id` avoids the scoping altogether.

### Use `scoped()`, not `singleton()`, for anything tenant-aware

Octane and queue workers keep the application in memory between requests and jobs. A `singleton()` that holds the current tenant, or anything derived from it, keeps the first tenant and serves it to the next one. Laravel resets `scoped()` bindings between requests and jobs, which is why the package registers its own services that way.

```php
$this->app->scoped(TenantBilling::class);    // fresh for every request and job
$this->app->singleton(TenantBilling::class); // leaks one tenant into the next
```

The same applies to static properties and to values captured in the constructor of a long-lived service: read `Tenancy::current()` when you need it, not when the object is built. Larastan's `checkOctaneCompatibility` option flags many of these cases.

### Keep tenant 404s uniform

When a request has no tenant, or the user isn't a member, the package returns a plain 404. That's deliberate: an unknown tenant and one you can't access must look identical, or the response tells an outsider which tenants exist. Keep it that way in your exception handling:

- Don't turn tenant 404s into a redirect with a flash message, or give them their own page. Any difference from your normal 404 gives away which tenants exist.
- Keep your error pages tenant-agnostic. Don't read the current tenant, query tenant-scoped models, or call `route()` for a tenant route without passing the tenant explicitly.

### Impersonating users

Impersonate by logging in as the user, the way impersonation packages swap the authenticated user on the guard. Then the tenant still comes from the URL and membership is still checked against the impersonated user, so staff see exactly what that user sees and nothing more. Don't use `Tenancy::crossTenant()` or `Tenancy::initialize()` to impersonate, and don't add a staff bypass to `tenant.member`.

A few things to handle in your app:

- **Don't change the user's landing preference.** `tenant.record` saves the current tenant as the user's preference on every page view, so browsing as the user moves their landing tenant. Skip recording while impersonating:

  ```php
  final class RecordsCurrentTenantUnlessImpersonating
  {
      public function __construct(private RecordsCurrentTenant $recorder) {}

      public function handle(Request $request, Closure $next): Response
      {
          if ($request->session()->has('impersonated_by')) {   // your impersonation check
              return $next($request);
          }

          return $this->recorder->handle($request, $next);
      }
  }
  ```

  The same goes for calls to `switchTo()` in your own code, such as a team switcher.

- **Start and stop impersonation outside tenant routes.** After starting, redirect to your `tenant.landing` route so the user's own landing tenant is picked. After stopping, redirect to a staff page. Returning to the tenant URL you were on gives the staff member a 404 unless they're a member.

- **Record who really acted.** While impersonating, `$request->user()` is the impersonated user, and queued jobs carry the tenant but not the impersonator. If an audit trail or a job needs to know who acted, pass the impersonator's id along explicitly.

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
