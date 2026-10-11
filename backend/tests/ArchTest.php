<?php

declare(strict_types=1);

arch('no debugging calls')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('domain enums are enums')
    ->expect('App\Domain\*\Enums')
    ->toBeEnums();

arch()->preset()->php();

arch()->preset()->security();
