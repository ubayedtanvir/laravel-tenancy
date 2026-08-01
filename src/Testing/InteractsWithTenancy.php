<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Exceptions\CrossTenantWriteDenied;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantContextMissing;
use UbayedTanvir\LaravelTenancy\Facades\Tenancy;

/**
 * @mixin TestCase
 */
trait InteractsWithTenancy
{
    protected function actingAsTenant(IsTenant $isTenant): static
    {
        Tenancy::initialize($isTenant);

        return $this;
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function assertTenantIsolated(string $model, IsTenant $a, IsTenant $b): void
    {
        /** @var Model $foreign */
        $foreign = Tenancy::runFor($b, fn () => $model::factory()->create());
        $foreignKey = $foreign->getTenantForeignKey();

        Tenancy::runFor($a, function () use ($model, $foreign, $foreignKey, $b): void {
            $this->assertNull($model::query()->find($foreign->getKey()), 'read isolation failed');
            $this->assertSame(0, $model::query()->count(), 'count isolation failed');

            $this->assertThrewCrossTenantWrite(
                fn () => $model::factory()->create([$foreignKey => $b->getKey()]),
            );
        });

        Tenancy::end();

        $this->assertThrewMissingContext(fn () => $model::query()->count());
    }

    private function assertThrewCrossTenantWrite(callable $callback): void
    {
        try {
            $callback();
        } catch (CrossTenantWriteDenied) {
            return;
        }

        $this->fail('Expected a CrossTenantWriteDenied when writing into another tenant.');
    }

    private function assertThrewMissingContext(callable $callback): void
    {
        try {
            $callback();
        } catch (TenantContextMissing) {
            return;
        }

        $this->fail('Expected a TenantContextMissing when querying with no tenant bound.');
    }
}
