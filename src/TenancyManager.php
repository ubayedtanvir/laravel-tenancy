<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Traits\Macroable;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Events\CrossTenantAccessGranted;
use UbayedTanvir\LaravelTenancy\Events\TenancyEnded;
use UbayedTanvir\LaravelTenancy\Events\TenancyInitialized;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;

final class TenancyManager
{
    use Macroable;

    /** The current tenant */
    private ?IsTenant $isTenant = null;

    /** Depth, not a boolean — crossTenant() calls nest. */
    private int $crossTenantDepth = 0;

    /** @var (Closure(Request): (IsTenant|null))|null */
    private ?Closure $customResolver = null;

    public function initialize(IsTenant $isTenant): void
    {
        if ($this->isTenant instanceof IsTenant
            && (string) $this->isTenant->getKey() === (string) $isTenant->getKey()
        ) {
            return;
        }

        if ($this->isTenant instanceof IsTenant) {
            $this->end();
        }

        $this->isTenant = $isTenant;

        Context::add('tenant_id', $isTenant->getKey());

        event(new TenancyInitialized($isTenant));
    }

    public function end(): void
    {
        if (! $this->isTenant instanceof IsTenant) {
            return;
        }

        $previous = $this->isTenant;
        $this->isTenant = null;

        Context::forget('tenant_id');

        event(new TenancyEnded($previous));
    }

    public function current(): ?IsTenant
    {
        return $this->isTenant;
    }

    public function currentOrFail(): IsTenant
    {
        return $this->isTenant ?? throw TenantContextMissing::forOperation('currentOrFail');
    }

    public function id(): int|string|null
    {
        return $this->isTenant?->getKey();
    }

    public function idOrFail(): int|string
    {
        return $this->currentOrFail()->getKey();
    }

    public function check(): bool
    {
        return $this->isTenant instanceof IsTenant;
    }

    public function is(IsTenant|int|string $tenant): bool
    {
        if (! $this->isTenant instanceof IsTenant) {
            return false;
        }

        $key = $tenant instanceof IsTenant ? $tenant->getKey() : $tenant;

        return (string) $this->isTenant->getKey() === (string) $key;
    }

    public function strict(): bool
    {
        return config()->boolean('tenancy.strict', default: true);
    }

    public function crossTenantEnabled(): bool
    {
        return $this->crossTenantDepth > 0;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function crossTenant(Closure $callback): mixed
    {
        event(new CrossTenantAccessGranted($this->isTenant));

        $this->crossTenantDepth++;

        try {
            return $callback();
        } finally {
            $this->crossTenantDepth--;
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(IsTenant): TReturn  $callback
     * @return TReturn
     */
    public function runFor(IsTenant $isTenant, Closure $callback): mixed
    {
        $previous = $this->isTenant;

        try {
            $this->initialize($isTenant);

            return $callback($isTenant);
        } finally {
            $previous instanceof IsTenant
                ? $this->initialize($previous)
                : $this->end();
        }
    }

    /**
     * Run a callback for every tenant, chunked.
     *
     * @param  Closure(IsTenant): void  $callback
     */
    public function each(Closure $callback, int $chunk = 100): void
    {
        $model = $this->tenantModel();

        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->chunkById(count: $chunk, callback: function (mixed $tenants) use ($callback): void {
                foreach ($tenants as $tenant) {
                    /** @var IsTenant $tenant */
                    $this->runFor($tenant, $callback);
                }
            });
    }

    /**
     * @param  Closure(Request): (IsTenant|null)  $callback
     */
    public function resolveUsing(Closure $callback): void
    {
        $this->customResolver = $callback;
    }

    /**
     * @return (Closure(Request): (IsTenant|null))|null
     */
    public function customResolver(): ?Closure
    {
        return $this->customResolver;
    }

    /**
     * The tenant foreign key: configured value, else Laravel's own BelongsTo
     * convention on the tenant model (Team => team_id).
     */
    public function foreignKey(): string
    {
        $configured = config('tenancy.tenant.foreign_key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->tenantModel()->getForeignKey();
    }

    private function tenantModel(): Model
    {
        $class = config('tenancy.tenant.model');

        throw_if(
            ! is_string($class) || ! is_subclass_of($class, Model::class),
            TenantContextMissing::class,
            'No tenant model configured. Set tenancy.tenant.model (run `php artisan tenancy:install`).'
        );

        return new $class;
    }
}
