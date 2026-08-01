<?php

declare(strict_types=1);

use UbayedTanvir\LaravelTenancy\Tests\Fixtures\Models\Tenant;

it('reports the resolved conventions for the chosen tenant model', function (): void {
    $this->artisan('tenancy:install', ['--model' => Tenant::class, '--force' => true])
        ->expectsOutputToContain('tenant_id')
        ->assertSuccessful();
});

it('fails for a class that is not an Eloquent model', function (): void {
    $this->artisan('tenancy:install', ['--model' => 'App\\Nope\\NotAModel'])
        ->assertFailed();
});
