<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use UbayedTanvir\LaravelTenancy\Contracts\IsTenant;

/**
 * Dispatched when the request tenant differs from the user's stored preference.
 */
final readonly class TenantContextChanged
{
    public function __construct(
        public Model $user,
        public ?IsTenant $from,
        public IsTenant $to,
        public Request $request,
    ) {}

    public function isFirstTenant(): bool
    {
        return ! $this->from instanceof IsTenant;
    }

    public function isExternalNavigation(): bool
    {
        $referer = $this->request->headers->get('referer');

        return $referer === null
            || parse_url($referer, PHP_URL_HOST) !== $this->request->getHost();
    }
}
