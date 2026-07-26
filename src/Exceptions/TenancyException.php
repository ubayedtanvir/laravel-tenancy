<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Exceptions;

use RuntimeException;

/**
 * Base exception for the package. Catch this to catch everything the package
 * throws.
 */
class TenancyException extends RuntimeException {}
