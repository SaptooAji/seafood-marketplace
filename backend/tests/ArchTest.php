<?php

declare(strict_types=1);

arch('no debugging calls')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch()->preset()->php();

arch()->preset()->security();
