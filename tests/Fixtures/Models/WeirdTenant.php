<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

/**
 * A key type the package cannot classify — must throw, never guess.
 */
final class WeirdTenant extends Model implements IsTenant
{
    protected $keyType = 'binary';

    protected $guarded = [];
}
