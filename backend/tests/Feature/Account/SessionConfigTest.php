<?php

declare(strict_types=1);

/*
 * SES-4: the web session cookie is out of reach of scripts, not sent on
 * cross-site requests that change state, and only sent over HTTPS when
 * SESSION_SECURE_COOKIE is on (required in production).
 */
it('keeps the session cookie away from scripts and cross-site posts', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

it('marks the session cookie secure when SESSION_SECURE_COOKIE is on', function (string $value, bool $secure) {
    putenv("SESSION_SECURE_COOKIE={$value}");

    try {
        $config = require config_path('session.php');
    } finally {
        putenv('SESSION_SECURE_COOKIE');
    }

    expect($config['secure'])->toBe($secure);
})->with([
    'on' => ['true', true],
    'off' => ['false', false],
]);

it('lists SESSION_SECURE_COOKIE in .env.example', function () {
    expect(file_get_contents(base_path('.env.example')))->toMatch('/^SESSION_SECURE_COOKIE=/m');
});
