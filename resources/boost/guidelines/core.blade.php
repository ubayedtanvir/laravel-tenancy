## Laravel Tenancy

Single-database, shared-schema multi-tenancy for Laravel. Row-level tenant isolation via global scopes, automatic write stamping, immutable tenant columns, and fail-closed defaults.

**Namespace:** `UbayedTanvir\LaravelTenancy`

### Core Concepts

- **`IsTenant`** — marker interface. Any Eloquent model can be a tenant. No methods to implement.
- **`BelongsToTenant`** — trait on scoped models. Adds a global scope, auto-stamps the tenant column on create, guards it from mass assignment, rejects cross-tenant writes, and makes the column immutable after creation.
- **`TenancyManager`** — scoped singleton holding the current tenant. Use the `Tenancy` facade or `tenant()` / `tenant_id()` helpers.

### Conventions

- The tenant foreign key follows Laravel's `getForeignKey()` convention. If the tenant model is `Team`, the column is `team_id`.
- Column type is inferred from the tenant model (`HasUuids` → uuid, `HasUlids` → ulid, otherwise bigInteger).
- Config lives in `config/tenancy.php`. Only the model class is required — everything else is derived.
- Strict mode is on by default. A scoped query without a tenant context throws `TenantContextMissing`.
- Cross-tenant access is always explicit via `Tenancy::crossTenant()`, `withoutTenancy()`, `acrossTenants()`, or `forTenant($tenant)`.

### Key Classes

| Purpose | Class / Trait |
|---|---|
| Tenant model interface | `Contracts\IsTenant` |
| Scope trait for models | `Concerns\BelongsToTenant` |
| Multi-tenant membership | `Contracts\TenantMembership`, `Concerns\HasTenants` |
| Current tenant tracking | `Concerns\TracksCurrentTenant` |
| Facade | `Facades\Tenancy` |
| Tenant-scoped cache | `Cache\TenantCache` (via `Cache::tenant()`) |
| Console trait | `Concerns\InteractsWithTenants` |
| Testing trait | `Testing\InteractsWithTenancy` |

### Migration Macros

@verbatim
<code-snippet name="Tenant column macro" lang="php">
$table->tenant();              // foreign key + index + cascade
$table->tenant('account_id');  // custom column name
$table->tenantKey();           // column only, no FK
$table->currentTenant();       // nullable, nullOnDelete (for landing preference)
</code-snippet>
@endverbatim

### Middleware Aliases

| Alias | Middleware |
|---|---|
| `tenant` | `IdentifyTenant` |
| `tenant.required` | `RequireTenant` |
| `tenant.member` | `EnsureTenantMember` |
| `tenant.record` | `RecordsCurrentTenant` |
| `tenant.landing` | `RedirectToCurrentTenant` |
| `tenant.strict-switch` | `RequireExplicitSwitch` |

### Common Patterns

- **Resolve from URL path (default):** `PathTenantResolver` reads from a route parameter.
- **Resolve from header:** `HeaderTenantResolver` reads `X-Tenant`.
- **Chain resolvers:** pass an array in config; first non-null wins.
- **Custom resolution:** `Tenancy::resolveUsing(fn (Request $r) => ...)`.
- **Tenant-aware queues:** automatic — jobs dispatched in a tenant context restore it on the worker. Opt out with `NotTenantAware`.
- **Tenant-scoped cache:** `Cache::tenant()->remember('key', $ttl, fn () => ...)`.
- **Run artisan in tenant context:** `php artisan tenancy:run "cache:clear" --tenant=acme`.
