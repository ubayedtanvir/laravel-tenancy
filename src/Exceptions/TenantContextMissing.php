<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Exceptions;

final class TenantContextMissing extends TenancyException
{
    /**
     * @param  class-string  $model
     */
    public static function forModel(string $model): self
    {
        return new self(\sprintf(
            'Model [%s] was queried or written with no tenant bound. Establish '.
            'context with Tenancy::initialize($tenant), or opt out explicitly '.
            'with ->withoutTenancy() / Tenancy::crossTenant(...).',
            $model,
        ));
    }

    public static function forOperation(string $operation): self
    {
        return new self(\sprintf(
            'Tenancy::%s() was called with no tenant bound. Establish context '.
            'with Tenancy::initialize($tenant) first.',
            $operation,
        ));
    }
}
