<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Support;

use Illuminate\Queue\Events\JobProcessing;
use UbayedTanvir\LaravelTenancy\Contracts\NotTenantAware;
use UbayedTanvir\LaravelTenancy\Contracts\TenantRepository;
use UbayedTanvir\LaravelTenancy\Exceptions\TenantNoLongerExists;
use UbayedTanvir\LaravelTenancy\TenancyManager;

/**
 * Stamps outgoing payloads with the tenant ID and restores context when
 * a job is processed.
 *
 * @internal
 */
final class TenantQueueBinder
{
    public const string KEY = 'tenant_id';

    /**
     * @return array<string, int|string>
     */
    public function payload(): array
    {
        $tenancyManager = resolve(TenancyManager::class);

        if (! $tenancyManager->queueTenantAware()) {
            return [];
        }

        $id = $tenancyManager->id();

        return $id === null ? [] : [self::KEY => $id];
    }

    public function restore(JobProcessing $jobProcessing): void
    {
        $tenancyManager = resolve(TenancyManager::class);

        if (! $tenancyManager->queueTenantAware() || $this->jobOptedOut($jobProcessing)) {
            return;
        }

        $id = $jobProcessing->job->payload()[self::KEY] ?? null;

        if (! \is_int($id) && ! \is_string($id)) {
            return;   // legitimately tenant-less
        }

        $tenant = resolve(TenantRepository::class)->findByKey($id);

        if ($tenant === null) {
            // Fail the job explicitly; running without a tenant would throw less clearly.
            $jobProcessing->job->fail(new TenantNoLongerExists($id));

            return;
        }

        $tenancyManager->initialize($tenant);
    }

    public function reset(): void
    {
        resolve(TenancyManager::class)->end();
    }

    private function jobOptedOut(JobProcessing $jobProcessing): bool
    {
        $name = $jobProcessing->job->resolveName();

        return $name !== null
            && class_exists($name)
            && is_subclass_of($name, NotTenantAware::class);
    }
}
