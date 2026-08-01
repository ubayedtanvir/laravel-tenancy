<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy;

use Illuminate\Contracts\Foundation\Application;
use UbayedTanvir\LaravelTenancy\Contracts\TenantResolver;
use UbayedTanvir\LaravelTenancy\Exceptions\TenancyException;
use UbayedTanvir\LaravelTenancy\Resolvers\ChainTenantResolver;

/**
 * Builds the active tenant resolver from the tenancy configuration.
 */
final readonly class ResolverFactory
{
    public function __construct(private Application $application) {}

    public function make(): TenantResolver
    {
        $config = config('tenancy.resolver');

        if (\is_array($config)) {
            $resolvers = [];

            foreach ($config as $class) {
                $resolvers[] = $this->makeSingle($class);
            }

            return new ChainTenantResolver($resolvers);
        }

        return $this->makeSingle($config);
    }

    /**
     * Instantiate and validate a single resolver class.
     *
     * @throws TenancyException
     */
    private function makeSingle(mixed $class): TenantResolver
    {
        throw_unless(
            \is_string($class),
            TenancyException::class,
            'tenancy.resolver must be a resolver class string or a list of them.'
        );

        $resolver = $this->application->make($class);

        if (! $resolver instanceof TenantResolver) {
            throw new TenancyException(\sprintf(
                'Tenant resolver [%s] must implement %s.', $class, TenantResolver::class,
            ));
        }

        return $resolver;
    }
}
