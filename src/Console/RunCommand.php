<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\TenancyManager;

#[Signature(signature: 'tenancy:run {task}
    {--tenant=* : One or more tenant route keys}
    {--all : Run for every tenant}
    {--continue-on-error : Keep going if a tenant fails}'
)]
#[Description(description: "Run an artisan command in each tenant's context")]
final class RunCommand extends Command
{
    public function handle(TenancyManager $tenancyManager, TenantRepository $tenantRepository): int
    {
        $task = $this->argument('task');

        if (! \is_string($task)) {
            $this->components->error('A command to run is required.');

            return self::FAILURE;
        }

        $continueOnError = (bool) $this->option('continue-on-error');

        if ((bool) $this->option('all')) {
            $tenancyManager->each(function (IsTenant $isTenant) use ($task): void {
                $this->runForTenant($isTenant, $task);
            });

            return self::SUCCESS;
        }

        $status = self::SUCCESS;

        foreach ($this->tenantKeys() as $key) {
            $tenant = $tenantRepository->findByRouteKey($key);

            if (! $tenant instanceof IsTenant) {
                $this->components->error(sprintf('Tenant [%s] was not found.', $key));
                $status = self::FAILURE;

                if (! $continueOnError) {
                    break;
                }

                continue;
            }

            $code = $tenancyManager->runFor($tenant, fn (): int => $this->runForTenant($tenant, $task));

            if ($code !== self::SUCCESS) {
                $status = $code;

                if (! $continueOnError) {
                    break;
                }
            }
        }

        return $status;
    }

    private function runForTenant(IsTenant $isTenant, string $task): int
    {
        $this->components->info(\sprintf('Running [%s] for tenant [%s]', $task, $isTenant->getRouteKey()));

        return $this->call($task);
    }

    /**
     * @return list<string>
     */
    private function tenantKeys(): array
    {
        $keys = $this->option('tenant');

        if (! \is_array($keys)) {
            return [];
        }

        return array_values(array_filter($keys, \is_string(...)));
    }
}
