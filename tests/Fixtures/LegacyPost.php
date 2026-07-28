<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;

/**
 * Legacy schema whose tenant column is not the conventional name.
 */
final class LegacyPost extends Model
{
    use BelongsToTenant;

    protected $table = 'legacy_posts';

    protected $guarded = [];

    protected string $tenantForeignKey = 'account_id';
}
