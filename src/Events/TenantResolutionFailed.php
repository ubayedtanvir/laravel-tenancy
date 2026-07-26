<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Events;

use Illuminate\Http\Request;

final readonly class TenantResolutionFailed
{
    public function __construct(public Request $request) {}
}
