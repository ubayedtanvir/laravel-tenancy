<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Events;

use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

final readonly class CrossTenantAccessGranted
{
    public function __construct(public ?IsTenant $tenant) {}
}
