<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Testing\TestResponse;

/*
 * SES-1: API tokens expire after 90 days unless SANCTUM_EXPIRATION says
 * otherwise, carry a prefix that secret scanners recognize, and expired
 * tokens are pruned every day.
 */

const TOKEN_LIFETIME_MINUTES = 60 * 24 * 90;

beforeEach(function () {
    User::factory()->create(['email' => 'budi@mail.com', 'password' => 'rahasia123']);
});

/**
 * Sign in through the API and return the plain token.
 */
function signInForToken(): string
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => 'budi@mail.com',
        'password' => 'rahasia123',
        'device_name' => 'Pixel 8',
    ])->assertOk()->json('token');
}

/**
 * Call a signed-in route with the token, as a fresh request would.
 */
function callWithToken(string $token): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->getJson('/api/v1/categories');
}

it('gives out tokens with the sfm_ prefix that work', function () {
    $token = signInForToken();

    // Sanctum's format is "<id>|<prefix><random>".
    expect($token)->toMatch('/^\d+\|sfm_[A-Za-z0-9]{40}/');
    callWithToken($token)->assertOk();
});

it('accepts a token until it is 90 days old and refuses it after', function () {
    $token = signInForToken();

    $this->travel(TOKEN_LIFETIME_MINUTES - 1)->minutes();
    callWithToken($token)->assertOk();

    $this->travel(2)->minutes();
    callWithToken($token)->assertUnauthorized();
});

it('reads the lifetime from SANCTUM_EXPIRATION, 90 days when unset', function (?string $value, int $minutes) {
    $original = [$_SERVER['SANCTUM_EXPIRATION'] ?? null, $_ENV['SANCTUM_EXPIRATION'] ?? null, getenv('SANCTUM_EXPIRATION')];

    if ($value === null) {
        unset($_SERVER['SANCTUM_EXPIRATION'], $_ENV['SANCTUM_EXPIRATION']);
        putenv('SANCTUM_EXPIRATION');
    } else {
        $_SERVER['SANCTUM_EXPIRATION'] = $_ENV['SANCTUM_EXPIRATION'] = $value;
        putenv("SANCTUM_EXPIRATION={$value}");
    }

    try {
        $config = require config_path('sanctum.php');
    } finally {
        [$server, $env, $process] = $original;
        if ($server === null) {
            unset($_SERVER['SANCTUM_EXPIRATION']);
        } else {
            $_SERVER['SANCTUM_EXPIRATION'] = $server;
        }
        if ($env === null) {
            unset($_ENV['SANCTUM_EXPIRATION']);
        } else {
            $_ENV['SANCTUM_EXPIRATION'] = $env;
        }
        putenv($process === false ? 'SANCTUM_EXPIRATION' : "SANCTUM_EXPIRATION={$process}");
    }

    expect($config['expiration'])->toBe($minutes);
})->with([
    'unset' => [null, TOKEN_LIFETIME_MINUTES],
    'one week' => ['10080', 10080],
]);

it('lists the token settings in .env.example', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->toMatch('/^SANCTUM_EXPIRATION=\d+$/m')
        ->toMatch('/^SANCTUM_TOKEN_PREFIX=sfm_$/m');
});

it('prunes expired tokens every day', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'sanctum:prune-expired'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
