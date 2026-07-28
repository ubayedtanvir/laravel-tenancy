<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

final class StringTenant extends Model implements IsTenant
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
