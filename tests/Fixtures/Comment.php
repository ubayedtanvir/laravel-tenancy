<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;

/**
 * A second scoped model sharing the tenant_id column name with Post, used to
 * prove the scope qualifies its column and joins do not raise "ambiguous
 * column tenant_id".
 */
final class Comment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
