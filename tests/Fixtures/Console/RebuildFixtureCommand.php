<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use UbayedTanvir\LaravelTenancy\Concerns\InteractsWithTenants;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

#[Signature(signature: 'test:rebuild')]
#[Description(description: 'Fixture command exercising InteractsWithTenants')]
final class RebuildFixtureCommand extends Command
{
    use InteractsWithTenants;

    protected function handleForTenant(IsTenant $isTenant): int
    {
        Cache::increment('rebuild_ticks');

        return self::SUCCESS;
    }
}
