<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Concerns;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Adds --tenant=* and --all to a console command and runs handleForTenant() in
 * each tenant's context.
 *
 * @mixin Command
 */
trait InteractsWithTenants
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('tenant', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'One or more tenant route keys');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Run for every tenant');
        $this->addOption('continue-on-error', null, InputOption::VALUE_NONE, 'Keep going if a tenant fails');
    }

    public function handle(): int
    {
        $tenancyManager = resolve(TenancyManager::class);
        $continueOnError = (bool) $this->option('continue-on-error');

        if ((bool) $this->option('all')) {
            $tenancyManager->each(function (IsTenant $isTenant): void {
                $this->handleForTenant($isTenant);
            });

            return Command::SUCCESS;
        }

        $tenantRepository = resolve(TenantRepository::class);
        $status = Command::SUCCESS;

        foreach ($this->tenantKeys() as $key) {
            $tenant = $tenantRepository->findByRouteKey($key);

            if ($tenant === null) {
                $this->components->error(sprintf('Tenant [%s] was not found.', $key));
                $status = Command::FAILURE;

                if (! $continueOnError) {
                    break;
                }

                continue;
            }

            $code = $tenancyManager->runFor($tenant, fn (): int => $this->handleForTenant($tenant));

            if ($code !== Command::SUCCESS) {
                $status = $code;

                if (! $continueOnError) {
                    break;
                }
            }
        }

        return $status;
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

    abstract protected function handleForTenant(IsTenant $isTenant): int;
}
