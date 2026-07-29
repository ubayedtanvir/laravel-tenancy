<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Database;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * Blueprint macros for tenant columns.
 *
 * @mixin Blueprint
 */
final class SchemaBlueprintMixin
{
    /**
     * Add a tenant column with an index and a cascading foreign key.
     */
    public function tenant(): Closure
    {
        return function (?string $column = null): ColumnDefinition {
            /** @var Blueprint $this */
            $column ??= TenantColumn::foreignKey();
            $columnDefinition = TenantColumn::add($this, $column);

            $this->foreign($column)
                ->references(TenantColumn::parentKey())
                ->on(TenantColumn::table())
                ->cascadeOnDelete();

            $this->index($column);

            return $columnDefinition;
        };
    }

    /**
     * Add a bare tenant column with no index and no foreign key constraint.
     */
    public function tenantKey(): Closure
    {
        return function (?string $column = null): ColumnDefinition {
            /** @var Blueprint $this */
            return TenantColumn::add($this, $column);
        };
    }

    /**
     * Drop the tenant column together with its index and foreign key.
     */
    public function dropTenant(): Closure
    {
        return function (?string $column = null): void {
            /** @var Blueprint $this */
            $column ??= TenantColumn::foreignKey();

            $this->dropForeign([$column]);
            $this->dropIndex([$column]);
            $this->dropColumn($column);
        };
    }

    /**
     * Add a nullable current-tenant column with a nullOnDelete foreign key.
     */
    public function currentTenant(): Closure
    {
        return function (?string $column = null): ColumnDefinition {
            /** @var Blueprint $this */
            $column ??= 'current_tenant_id';

            $columnDefinition = TenantColumn::add($this, $column)->nullable();

            $this->foreign($column)
                ->references(TenantColumn::parentKey())
                ->on(TenantColumn::table())
                ->nullOnDelete();

            return $columnDefinition;
        };
    }

    /**
     * Drop the current-tenant column together with its foreign key.
     */
    public function dropCurrentTenant(): Closure
    {
        return function (?string $column = null): void {
            /** @var Blueprint $this */
            $this->dropConstrainedForeignId($column ?? 'current_tenant_id');
        };
    }
}
