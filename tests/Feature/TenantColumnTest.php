<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Database\TenantColumn;
use UbayedTanvir\LaravelTenancy\Exceptions\TenancyException;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\BigintTenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\StringTenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\UlidTenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\UuidTenant;
use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\WeirdTenant;

it('infers the column type from the tenant key', function (string $fixture, string $expected): void {
    expect(TenantColumn::keyType(new $fixture))->toBe($expected);
})->with([
    'uuid' => [UuidTenant::class, TenantColumn::UUID],
    'ulid' => [UlidTenant::class, TenantColumn::ULID],
    'auto-increment id' => [Tenant::class, TenantColumn::ID],
    'non-incrementing int' => [BigintTenant::class, TenantColumn::BIGINT],
    'string' => [StringTenant::class, TenantColumn::STRING],
]);

it('throws on an unclassifiable key type', function (): void {
    expect(fn (): string => TenantColumn::keyType(new WeirdTenant))
        ->toThrow(TenancyException::class);
});

it('honours an explicit key_type config over inference', function (): void {
    config(['tenancy.tenant.key_type' => TenantColumn::ULID]);

    expect(TenantColumn::keyType(new Tenant))->toBe(TenantColumn::ULID);
});

it('describes the resolved column type', function (): void {
    expect(TenantColumn::describe(new Tenant))->toBe('unsignedBigInteger (foreignId)')
        ->and(TenantColumn::describe(new UuidTenant))->toBe(TenantColumn::UUID)
        ->and(TenantColumn::describe(new StringTenant))->toBe('string(36)');
});

it('derives the foreign key and table from convention', function (): void {
    expect(TenantColumn::foreignKey(new Tenant))->toBe('tenant_id')
        ->and(TenantColumn::table(new Tenant))->toBe('tenants')
        ->and(TenantColumn::parentKey(new Tenant))->toBe('id');
});
