<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
 * LOG-3 and CONF-2 on the failure paths that log or render errors: no
 * password, token, key, or bank account in the log, an exception, or a
 * JSON error. Gemini's key is covered in SuggestProductDescriptionTest.
 */

const LEAK_ID_TOKEN = 'eyJleak.secret-google-id-token.signature';
const LEAK_PASSWORD = 'Rahasia-Sekali-77';
const LEAK_TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo*';

beforeEach(function () {
    config(['app.debug' => false, 'services.google.allowed_client_ids' => ['web-client-id.apps.googleusercontent.com']]);

    $this->logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) {
        $this->logged[] = $event->message.' '.leakContextText($event->context);
    });
});

/**
 * The log context as text, with every exception in a chain spelled out:
 * class, message, and stack trace.
 *
 * @param  array<string, mixed>  $context
 */
function leakContextText(array $context): string
{
    $text = '';

    foreach ($context as $value) {
        for ($e = $value instanceof Throwable ? $value : null; $e !== null; $e = $e->getPrevious()) {
            $text .= ' '.$e::class.' '.$e->getMessage().' '.$e->getTraceAsString();
        }

        if (! $value instanceof Throwable) {
            $text .= ' '.json_encode($value);
        }
    }

    return $text;
}

describe('Google sign-in', function () {
    it('keeps the ID token out of the log and the response when verification fails', function (Closure $googleResponse, int $status) {
        Http::fake([LEAK_TOKENINFO_URL => $googleResponse]);

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => LEAK_ID_TOKEN, 'device_name' => 'Pixel 8'])
            ->assertStatus($status);

        expect($response->getContent())->not->toContain(LEAK_ID_TOKEN)
            ->and(implode("\n", $this->logged))->not->toContain(LEAK_ID_TOKEN);
    })->with([
        // A real connection error names the URL, and with it the token.
        'connection lost' => [fn () => fn () => throw new ConnectionException('cURL error 28: timed out for https://oauth2.googleapis.com/tokeninfo?id_token='.LEAK_ID_TOKEN), 503],
        'Google server error' => [fn () => Http::response('upstream error', 500), 503],
        'token rejected' => [fn () => Http::response(['error' => 'invalid_token', 'error_description' => 'Invalid Value'], 400), 422],
        'wrong audience' => [fn () => Http::response(['aud' => 'someone-else', 'sub' => '1', 'email' => 'budi@gmail.com', 'iss' => 'accounts.google.com', 'exp' => (string) now()->addHour()->timestamp]), 422],
    ]);
});

describe('password', function () {
    it('keeps the password out of the log and the response when signing in fails', function (Closure $email) {
        User::factory()->create(['email' => 'budi@mail.com', 'password' => 'yang-benar-123']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $email(), 'password' => LEAK_PASSWORD, 'device_name' => 'Pixel 8'])
            ->assertUnprocessable();

        expect($response->getContent())->not->toContain(LEAK_PASSWORD)
            ->and(implode("\n", $this->logged))->not->toContain(LEAK_PASSWORD);
    })->with([
        'wrong password' => [fn () => 'budi@mail.com'],
        'unknown email' => [fn () => 'tidakada@mail.com'],
    ]);

    it('keeps the password out of the log and the response when registering fails', function () {
        User::factory()->create(['email' => 'budi@mail.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@mail.com',
            'phone' => '081234567890',
            'password' => LEAK_PASSWORD,
            'password_confirmation' => LEAK_PASSWORD.'-beda',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable();

        expect($response->getContent())->not->toContain(LEAK_PASSWORD)
            ->and(implode("\n", $this->logged))->not->toContain(LEAK_PASSWORD);
    });
});

describe('unexpected errors', function () {
    it('answers an API error with a generic message when debug is off', function () {
        Route::middleware('api')->get('/api/v1/__leak-test', function () {
            throw new RuntimeException('Gagal: token '.LEAK_ID_TOKEN.', rekening 9876543210');
        });

        $response = $this->getJson('/api/v1/__leak-test')->assertStatus(500);

        expect($response->json())->toBe(['message' => 'Server Error'])
            ->and($response->getContent())->not->toContain('9876543210')
            // Proves the listener sees reported exceptions, so the other tests are not empty passes.
            ->and($this->logged)->toHaveCount(1);
    });
});
