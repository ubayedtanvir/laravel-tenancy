<?php

/**
 * PHPStan bootstrap — registers Blueprint macros before static analysis.
 */

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use UbayedTanvir\LaravelTenancy\Database\SchemaBlueprintMixin;

Blueprint::mixin(new SchemaBlueprintMixin);
