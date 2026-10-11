<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Boot the app service provider as if running in the given environment.
 */
function bootProviderIn(string $environment): void
{
    app()->detectEnvironment(fn () => $environment);
    (new AppServiceProvider(app()))->boot();
}

/**
 * Read the prohibition flag directly, so the test never runs a destructive command.
 *
 * @param  class-string  $command
 */
function isProhibited(string $command): bool
{
    return (bool) (new ReflectionProperty($command, 'prohibitedFromRunning'))->getValue();
}

// The flags are static and outlive the test, so restore the testing defaults.
afterEach(function () {
    DB::prohibitDestructiveCommands(false);
    Model::shouldBeStrict();
});

$destructiveCommands = [
    'migrate:fresh' => FreshCommand::class,
    'migrate:refresh' => RefreshCommand::class,
    'migrate:reset' => ResetCommand::class,
    'migrate:rollback' => RollbackCommand::class,
    'db:wipe' => WipeCommand::class,
];

it('prohibits destructive database commands in production', function (string $command) {
    bootProviderIn('production');

    expect(isProhibited($command))->toBeTrue();
})->with($destructiveCommands);

it('allows destructive database commands outside production', function (string $environment, string $command) {
    bootProviderIn($environment);

    expect(isProhibited($command))->toBeFalse();
})->with(['local', 'testing'])->with($destructiveCommands);
