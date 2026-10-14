<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Sanctum\Sanctum;

/*
 * RATE-2: every signed-in API route shares a limit of 60 requests a minute
 * per user; the sign-in routes keep their own limiters.
 */

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

/**
 * Send the given number of requests to an API route as the signed-in user.
 */
function spendApiRequests(int $count): void
{
    foreach (range(1, $count) as $_) {
        test()->getJson('/api/v1/categories')->assertOk();
    }
}

it('allows 60 requests a minute and refuses the 61st with Retry-After', function () {
    spendApiRequests(60);

    $this->getJson('/api/v1/categories')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('counts each user on their own', function () {
    spendApiRequests(60);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/categories')->assertOk();
});

it('allows the user again after a minute', function () {
    spendApiRequests(60);

    $this->travel(61)->seconds();

    $this->getJson('/api/v1/categories')->assertOk();
});

it('puts the limit on every signed-in API route and leaves the sign-in routes to their own', function () {
    $apiRoutes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/'));

    $signedIn = $apiRoutes->filter(fn (Route $route) => in_array('auth:sanctum', $route->gatherMiddleware(), true));
    $signIn = $apiRoutes->filter(fn (Route $route) => in_array($route->getName(), ['api.auth.register', 'api.auth.login', 'api.auth.google'], true));

    // No API route is left without a limiter.
    expect($signedIn->count() + $signIn->count())->toBe($apiRoutes->count())
        ->and($signedIn)->not->toBeEmpty()
        ->and($signedIn->reject(fn (Route $route) => in_array('throttle:api', $route->gatherMiddleware(), true))->keys()->all())->toBe([])
        ->and($signIn)->toHaveCount(3)
        ->and($signIn->filter(fn (Route $route) => in_array('throttle:api', $route->gatherMiddleware(), true))->keys()->all())->toBe([]);
});
