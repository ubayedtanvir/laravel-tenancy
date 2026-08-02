<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;
use UbayedTanvir\LaravelTenancy\Exceptions\TenancyException;

arch('no debugging statements ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export'])
    ->not->toBeUsed();

arch('strict types everywhere')
    ->expect('UbayedTanvir\LaravelTenancy')
    ->toUseStrictTypes();

arch('concerns are traits')
    ->expect('UbayedTanvir\LaravelTenancy\Concerns')
    ->toBeTraits();

arch('contracts are interfaces')
    ->expect('UbayedTanvir\LaravelTenancy\Contracts')
    ->toBeInterfaces();

arch('events are final')
    ->expect('UbayedTanvir\LaravelTenancy\Events')
    ->toBeFinal();

arch('middleware are final')
    ->expect('UbayedTanvir\LaravelTenancy\Http\Middleware')
    ->toBeFinal();

arch('`TenancyException` extends `RuntimeException`')
    ->expect(TenancyException::class)
    ->toExtend(RuntimeException::class);

arch('Exception classes extends `TenancyException`')
    ->expect('UbayedTanvir\LaravelTenancy\Exceptions')
    ->toExtend(TenancyException::class);

arch('resolvers extends `TenantResolver` and are final')
    ->expect('UbayedTanvir\LaravelTenancy\Resolvers')
    ->toExtend(TenantResolver::class)
    ->toBeFinal();
