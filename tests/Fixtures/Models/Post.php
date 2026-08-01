<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\PostFactory;

#[UseFactory(factoryClass: PostFactory::class)]
final class Post extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $guarded = [];
}
