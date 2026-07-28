<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

final class UlidTenant extends Model implements IsTenant
{
    use HasUlids;

    protected $guarded = [];
}
