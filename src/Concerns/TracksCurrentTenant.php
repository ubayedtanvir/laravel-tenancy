<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantMembership;

/**
 * Tracks the user's last-active tenant as a landing preference.
 *
 * This is a UI convenience, not an authorization input. The current URL remains
 * authoritative for tenant scoping; this column only answers where to redirect
 * a user who arrives without naming a tenant.
 *
 * @mixin Model
 */
trait TracksCurrentTenant
{
    /**
     * Guard the current tenant column against mass assignment.
     */
    public function initializeTracksCurrentTenant(): void
    {
        $this->mergeGuarded([
            $this->getCurrentTenantColumn(),
        ]);
    }

    public function getCurrentTenantColumn(): string
    {
        if (property_exists($this, 'currentTenantColumn')
            && \is_string($this->currentTenantColumn)
            && $this->currentTenantColumn !== ''
        ) {
            return $this->currentTenantColumn;
        }

        return 'current_tenant_id';
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function currentTenant(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('tenancy.tenant.model');

        return $this->belongsTo($model, $this->getCurrentTenantColumn());
    }

    public function currentTenantIs(IsTenant $isTenant): bool
    {
        $stored = $this->getAttribute($this->getCurrentTenantColumn());

        return \is_scalar($stored) && (string) $stored === (string) $isTenant->getKey();
    }

    /**
     * Record a tenant as this user's landing preference.
     *
     * Uses saveQuietly to avoid triggering model observers and broadcasts for
     * what is bookkeeping, not a domain event.
     */
    public function switchTo(IsTenant $isTenant): static
    {
        $this->forceFill([
            $this->getCurrentTenantColumn() => $isTenant->getKey(),
        ])->saveQuietly();

        return $this;
    }

    public function forgetCurrentTenant(): static
    {
        $this->forceFill([
            $this->getCurrentTenantColumn() => null,
        ])->saveQuietly();

        return $this;
    }

    /**
     * Resolve the user's landing tenant, healing a stale preference if necessary.
     *
     * Returns null only when the user has no accessible tenant.
     */
    public function resolveLandingTenant(): ?IsTenant
    {
        $tenant = $this->currentTenant()->first();

        if ($tenant instanceof IsTenant && $this->canAccessTenant($tenant)) {
            return $tenant;
        }

        $fallback = $this->defaultLandingTenant();

        if ($fallback !== null) {
            $this->switchTo($fallback);
        } elseif ($tenant !== null) {
            $this->forgetCurrentTenant();
        }

        return $fallback;
    }

    protected function defaultLandingTenant(): ?IsTenant
    {
        return $this instanceof IsTenant ? $this : null;
    }

    private function canAccessTenant(IsTenant $isTenant): bool
    {
        if ($this instanceof IsTenant) {
            $thisKey = $this->getKey();
            $thatKey = $isTenant->getKey();

            return \is_scalar($thisKey) && \is_scalar($thatKey)
                && (string) $thisKey === (string) $thatKey;
        }

        return $this instanceof TenantMembership && $this->belongsToTenant($isTenant);
    }
}
