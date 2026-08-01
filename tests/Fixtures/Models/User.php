<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use UbayedTanvir\LaravelTenancy\Concerns\HasTenants;
use UbayedTanvir\LaravelTenancy\Concerns\TracksCurrentTenant;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantMembership;

final class User extends Authenticatable implements TenantMembership
{
    use HasTenants;
    use TracksCurrentTenant;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    protected function defaultLandingTenant(): ?IsTenant
    {
        return $this->tenants()->first();
    }
}
