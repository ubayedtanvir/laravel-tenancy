<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;

final class CrossTenantWriteDenied extends TenancyException
{
    public static function forModel(Model $model, string $assigned, string $current): self
    {
        return new self(\sprintf(
            'Attempted to write [%s] into tenant [%s] while tenant [%s] is '.
            'active. If this is deliberate, wrap it in Tenancy::crossTenant(...).',
            $model::class,
            $assigned,
            $current,
        ));
    }
}
