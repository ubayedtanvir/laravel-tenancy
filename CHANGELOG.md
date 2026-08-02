# Changelog

All notable changes to `laravel-tenancy` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Core
- `TenancyManager` — scoped singleton that holds the current tenant. Supports `initialize()`, `end()`, `runFor()` (with automatic restore), `each()` (chunked iteration), and depth-counted `crossTenant()` for nesting. Bound as `scoped()` for Octane safety.
- `Tenancy` facade with `current()`, `currentOrFail()`, `id()`, `idOrFail()`, `initialized()`, `is()`, `crossTenant()`, `runFor()`, `each()`, `resolveUsing()`, and `foreignKey()`.
- `tenant()` and `tenant_id()` global helpers.
- `IsTenant` marker interface — no methods required, any Eloquent model can be a tenant.
- `TenantMembership` contract for models that belong to multiple tenants (team switcher pattern).
- `NotTenantAware` marker interface to opt jobs out of tenant context propagation.
- Laravel `Context` integration — `tenant_id` is added/removed from the context bag on initialize/end.

#### Scoping and Write Guards
- `BelongsToTenant` trait — global scope filtering, auto-stamping on create, `CrossTenantWriteDenied` on wrong-tenant writes, `TenantOwnershipImmutable` on tenant column changes, mass-assignment guard on the foreign key.
- `TenantScope` — qualified-column read filter. Throws `TenantContextMissing` in strict mode, warns once per model in lenient mode, suspended inside `crossTenant()`.
- `withoutTenancy()`, `acrossTenants()`, and `forTenant($tenant)` query scopes on scoped models.
- Per-model `$tenantForeignKey` override for legacy schemas with non-standard column names.

#### Schema and Migrations
- `TenantColumn` — single source of truth for column type inference. Reads config, then checks `HasUuids`/`HasUlids`/key metadata. Never falls back silently.
- `$table->tenant()` Blueprint macro — typed column + index + cascading FK. Returns the column definition so `->nullable()` chains. Accepts an optional custom column name.
- `$table->tenantKey()` — column only, no FK or index.
- `$table->currentTenant()` — nullable + `nullOnDelete` for the landing-preference column.
- `$table->dropTenant()` and `$table->dropCurrentTenant()` — drops FK + index before column for SQLite/MySQL compatibility.

#### Resolution
- `PathTenantResolver` — resolves from a route parameter (default).
- `HeaderTenantResolver` — resolves from an HTTP header (`X-Tenant`).
- `ChainTenantResolver` — tries multiple resolvers, first non-null wins.
- `Tenancy::resolveUsing()` — closure-based resolution for custom strategies.
- `EloquentTenantRepository` with per-request memo, route-key-to-id cache, and negative miss caching.
- `TenantCacheInvalidator` — listens for tenant model updates/deletes and invalidates both old and new route keys.

#### HTTP Middleware
- `IdentifyTenant` — resolves and binds the tenant; hands the model to `SubstituteBindings`; ends context in `terminate()`. Dev-time guard throws if it runs after `SubstituteBindings`.
- `RequireTenant` — returns 404 when no tenant is bound.
- `EnsureTenantMember` — returns 404 (not 403, to avoid customer enumeration) for non-members. Identity path so tenant-is-user apps need no pivot table.
- `RecordsCurrentTenant` — updates the user's landing preference on safe (GET) requests. Fires `TenantContextChanged` synchronously, defers the actual write.
- `RedirectToCurrentTenant` — sends tenant-less requests to the user's last tenant.
- `RequireExplicitSwitch` — opt-in friction gate for hard tenant boundaries; preserves the intended URL in the session.
- Middleware aliases registered automatically: `tenant`, `tenant.required`, `tenant.member`, `tenant.record`, `tenant.landing`, `tenant.strict-switch`.

#### Current Tenant Tracking
- `TracksCurrentTenant` trait — `switchTo()` (via `saveQuietly`), `currentTenantIs()`, `forgetCurrentTenant()`, and self-healing `resolveLandingTenant()` that recovers from deleted tenants and revoked memberships.
- `HasTenants` trait — many-to-many `tenants()` relation and `belongsToTenant()` check.
- `TenantContextChanged` event with `isFirstTenant()` and `isExternalNavigation()` helpers.

#### Queues
- `TenantQueueBinder` — stamps tenant id into every queue payload via `Queue::createPayloadUsing()`. Covers jobs, closures, mailables, notifications, chains, and batches. Restores context on `before`, resets on `after`/`failing`/`exceptionOccurred`/`looping`.
- Jobs whose tenant was deleted between dispatch and processing fail with `TenantNoLongerExists`.
- `NotTenantAware` interface to opt individual jobs out.

#### Console
- `tenancy:run` command — runs any artisan command inside one or more tenant contexts. Supports `--tenant=slug` (repeatable), `--all`, and `--continue-on-error`.
- `InteractsWithTenants` trait for custom commands — adds tenant options automatically, calls `handleForTenant()` per tenant.
- `tenancy:install` — detects the tenant model, publishes config, prints resolved conventions.
- `tenancy:audit` — six checks over live schema and models: missing `BelongsToTenant` trait, non-composite unique indexes, missing leading index, column type drift, missing foreign key, and strict mode status. Supports `--json` and `--fail-on=warn|error` for CI.

#### Cache
- `Cache::tenant()` macro — returns a `TenantCache` decorator that prefixes keys with the tenant id. Never mutates the global cache prefix. Throws `TenantContextMissing` when no tenant is bound.

#### Testing
- `InteractsWithTenancy` trait with `actingAsTenant()` and `assertTenantIsolated()` — verifies read isolation, count isolation, cross-tenant write rejection, and missing-context throw in a single call.

#### Events
- `TenancyInitialized`
- `TenancyEnded`
- `CrossTenantAccessGranted`
- `TenantResolutionFailed`
- `TenantContextChanged`

#### Exceptions
- `TenancyException` (base)
- `TenantContextMissing`
- `CrossTenantWriteDenied`
- `TenantOwnershipImmutable`
- `TenantNotResolvable`
- `TenantNoLongerExists`
