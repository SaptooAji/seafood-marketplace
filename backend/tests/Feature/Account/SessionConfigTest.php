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
    // env() reads $_SERVER and $_ENV before getenv(), and a .env copied from
    // .env.example (as in CI) already sets this key there.
    $original = [$_SERVER['SESSION_SECURE_COOKIE'] ?? null, $_ENV['SESSION_SECURE_COOKIE'] ?? null, getenv('SESSION_SECURE_COOKIE')];
    $_SERVER['SESSION_SECURE_COOKIE'] = $_ENV['SESSION_SECURE_COOKIE'] = $value;
    putenv("SESSION_SECURE_COOKIE={$value}");

    try {
        $config = require config_path('session.php');
    } finally {
        [$server, $env, $process] = $original;
        if ($server === null) {
            unset($_SERVER['SESSION_SECURE_COOKIE']);
        } else {
            $_SERVER['SESSION_SECURE_COOKIE'] = $server;
        }
        if ($env === null) {
            unset($_ENV['SESSION_SECURE_COOKIE']);
        } else {
            $_ENV['SESSION_SECURE_COOKIE'] = $env;
        }
        putenv($process === false ? 'SESSION_SECURE_COOKIE' : "SESSION_SECURE_COOKIE={$process}");
    }

    expect($config['secure'])->toBe($secure);
})->with([
    'on' => ['true', true],
    'off' => ['false', false],
]);

it('lists SESSION_SECURE_COOKIE in .env.example', function () {
    expect(file_get_contents(base_path('.env.example')))->toMatch('/^SESSION_SECURE_COOKIE=/m');
});
