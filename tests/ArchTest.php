<?php

declare(strict_types=1);

arch('no debugging statements ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export'])
    ->not->toBeUsed();
