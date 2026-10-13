<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(array $overrides = []): SocialiteUser
{
    return SocialiteUser::fake(array_merge([
        'id' => '109876543210987654321',
        'name' => 'Budi Santoso',
        'email' => 'budi@gmail.com',
        'avatar' => 'https://lh3.googleusercontent.com/a/budi.jpg',
        // Google returns this in the raw user payload.
        'email_verified' => true,
    ], $overrides));
}

it('names the sign-in route login', function () {
    expect(route('login'))->toEndWith('/masuk');
});

it('redirects to Google from /masuk', function () {
    config(['services.google' => [
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'redirect' => 'http://localhost/auth/google/callback',
    ]]);

    $response = $this->get('/masuk')->assertRedirect();

    expect($response->headers->get('Location'))->toStartWith('https://accounts.google.com/');
});

it('signs the user in after a successful callback', function () {
    Socialite::fake('google', fakeGoogleUser());

    $this->get('/auth/google/callback')->assertRedirect('/');

    $this->assertAuthenticatedAs(User::query()->where('email', 'budi@gmail.com')->sole());
});

it('returns to the page the guest wanted before signing in', function () {
    Socialite::fake('google', fakeGoogleUser());

    $this->withSession(['url.intended' => url('/checkout')])
        ->get('/auth/google/callback')
        ->assertRedirect('/checkout');
});

it('keeps the user a guest when they cancel on Google', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get('/auth/google/callback?error=access_denied')
        ->assertRedirect('/')
        ->assertSessionHas('error');

    $this->assertGuest();
});

it('keeps the user a guest when the Google email is not verified', function () {
    Socialite::fake('google', fakeGoogleUser(['email_verified' => false]));

    $this->get('/auth/google/callback')
        ->assertRedirect('/')
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('sends guests on protected web pages to /masuk', function () {
    Route::middleware(['web', 'auth'])->get('/_test/akun', fn () => 'ok');

    $this->get('/_test/akun')->assertRedirect('/masuk');
});

it('gives the session a new id when the user signs in', function () {
    Socialite::fake('google', fakeGoogleUser());
    $this->startSession();
    $before = session()->getId();
    $cookie = config('session.cookie');

    // Control: without a sign-in, the same session cookie keeps its id.
    $this->withCookie($cookie, $before)->get('/');
    expect(session()->getId())->toBe($before);

    $this->withCookie($cookie, $before)->get('/auth/google/callback')->assertRedirect('/');

    $this->assertAuthenticated();
    expect(session()->getId())->not->toBe($before);
});
