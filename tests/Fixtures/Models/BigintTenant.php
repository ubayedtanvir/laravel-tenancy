<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

/**
 * Integer key that is not auto-incrementing (e.g. a Snowflake id).
 */
final class BigintTenant extends Model implements IsTenant
{
    public $incrementing = false;

    protected $guarded = [];
}
