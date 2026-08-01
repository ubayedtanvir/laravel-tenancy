<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Resolvers\PathTenantResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that implements `IsTenant` contract.
    |
    | Every value below is optional — when null, the package derives it from
    | Laravel's own conventions on this model. A consumer following Laravel
    | conventions configures nothing but the model.
    |
    */

    'tenant' => [
        // The tenant model
        'model' => env('TENANCY_MODEL', 'App\\Models\\User'),

        // The foreign key for the tenant model
        'foreign_key' => null,

        // model key type (uuid|ulid|id|bigInteger|string)
        'key_type' => null,

        // only used for plain-string keys
        'key_length' => 36,

        // null => $tenant->getRouteKeyName()
        'route_key' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    |
    | true  => a scoped query with no bound tenant throws.
    | false => the query is left unscoped and a warning is emitted once per
    |          process. Documented as a migration aid, never an operating mode;
    |
    */

    'strict' => env('TENANCY_STRICT', default: true),

    /*
    |--------------------------------------------------------------------------
    | Resolution
    |--------------------------------------------------------------------------
    |
    | A single resolver class, or an array of them (first non-null wins).
    | Closure-based resolution is registered in a service provider via
    | Tenancy::resolveUsing() — never here, because closures break config:cache.
    |
    */

    'resolver' => PathTenantResolver::class,

    // Route parameter carrying the tenant route key, e.g. /{tenant}/dashboard
    'route_parameter' => 'tenant',

    // Header read by HeaderTenantResolver
    'header' => 'X-Tenant',

    /*
    |--------------------------------------------------------------------------
    | Current-tenant landing
    |--------------------------------------------------------------------------
    |
    | Routes consumed by the context-persistence middleware. These are landing
    | preferences only — never an input to scoping or authorization.
    |
    */

    'landing_route' => 'tenant.dashboard',

    'switch_confirmation_route' => 'tenant.switch.confirm',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Used by the tenant repository (caches route-key => id) and Cache::tenant().
    |
    */

    'cache' => [
        'store' => null,   // null => application default
        'ttl' => 3600,
    ],
];
