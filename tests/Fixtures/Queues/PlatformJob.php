<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Queues;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use UbayedTanvir\LaravelTenancy\Contracts\NotTenantAware;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;

final class PlatformJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        Cache::put('platform_bound', Tenancy::initialized());
    }
}
