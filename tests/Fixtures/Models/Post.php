<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;

final class Post extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
