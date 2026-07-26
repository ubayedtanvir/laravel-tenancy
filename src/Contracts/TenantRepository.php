<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Contracts;

interface TenantRepository
{
    public function findByRouteKey(string $key): ?IsTenant;

    public function findByKey(int|string $id): ?IsTenant;

    public function forget(IsTenant $isTenant): void;
}
