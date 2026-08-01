<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Exceptions;

final class TenantNoLongerExists extends TenancyException
{
    public function __construct(int|string $id)
    {
        parent::__construct(\sprintf(
            'Tenant [%s] no longer exists.',
            $id,
        ));
    }
}
