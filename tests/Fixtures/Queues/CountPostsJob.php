<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Queues;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Post;

final class CountPostsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('post_count', Post::query()->count());
    }
}
