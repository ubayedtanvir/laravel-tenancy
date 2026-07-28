<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Database;

use Illuminate\Support\Facades\Log;

/**
 * Lenient mode (strict = false) is a migration aid, not an operating mode.
 * When a scoped model is queried with no tenant bound, warn once per model per
 * process so the fail-open behaviour cannot be adopted silently.
 *
 * @internal
 */
final class LenientModeWarning
{
    /** @var array<string, true> */
    private static array $warned = [];

    public static function emitOnce(string $model): void
    {
        if (isset(self::$warned[$model])) {
            return;
        }

        self::$warned[$model] = true;

        Log::warning(\sprintf(
            'Tenancy strict mode is off: [%s] was queried with no tenant bound and '.
            'returned unscoped. This is a migration aid, not a supported mode — '.
            'set tenancy.strict = true once your context is always established.',
            $model,
        ));
    }

    /**
     * Reset the per-process memo. For tests only.
     */
    public static function flush(): void
    {
        self::$warned = [];
    }
}
