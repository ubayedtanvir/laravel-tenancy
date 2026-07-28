<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

final class UuidTenant extends Model implements IsTenant
{
    use HasUuids;

    protected $guarded = [];
}
