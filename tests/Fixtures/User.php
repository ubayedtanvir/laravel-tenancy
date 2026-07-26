<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use UbayedTanvir\LaravelTenancy\Concerns\HasTenants;
use UbayedTanvir\LaravelTenancy\Contracts\TenantMembership;

final class User extends Authenticatable implements TenantMembership
{
    use HasTenants;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];
}
