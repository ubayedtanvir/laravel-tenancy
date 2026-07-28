<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;

final class TenantOwnershipImmutable extends TenancyException
{
    public static function forModel(Model $model): self
    {
        return new self(\sprintf(
            'The tenant key on [%s] cannot be changed after creation. A row '.
            'belongs to exactly one tenant for its lifetime.',
            $model::class,
        ));
    }
}
