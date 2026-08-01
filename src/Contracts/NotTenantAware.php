<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Contracts;

/**
 * Marker interface for jobs that should not inherit a tenant context
 * from the queue payload.
 */
interface NotTenantAware {}
