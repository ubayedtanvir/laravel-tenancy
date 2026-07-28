<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Exceptions;

final class TenantNotResolvable extends TenancyException
{
    public static function forRouteKey(string $key): self
    {
        return new self(\sprintf('No tenant could be resolved for [%s].', $key));
    }
}
