<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::get('protected', fn () => ['ok' => true])->middleware('auth:sanctum');
        Route::post('validate', fn (Request $request) => $request->validate(['name' => 'required']));
        Route::get('forbidden', fn () => abort(403));
        Route::get('error', fn () => throw new RuntimeException('Sensitive failure detail'));
    });
});

it('returns JSON 404 for an unknown API route without an Accept header', function () {
    $this->get('/api/v1/route-yang-tidak-ada')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('returns JSON 401 for a protected route without a token', function () {
    $this->get('/api/v1/_test/protected')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns JSON 422 with field errors when validation fails', function () {
    $this->post('/api/v1/_test/validate')
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});

it('returns JSON 403 when access is forbidden', function () {
    $this->get('/api/v1/_test/forbidden')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('hides exception details in JSON 500 responses when debug is off', function () {
    config(['app.debug' => false]);

    $response = $this->get('/api/v1/_test/error')
        ->assertInternalServerError()
        ->assertExactJson(['message' => 'Server Error']);

    expect($response->getContent())->not->toContain('Sensitive failure detail');
});

it('keeps HTML responses for unknown web routes', function () {
    $this->get('/halaman-tidak-ada')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

it('lets a valid Sanctum token through a protected route', function () {
    $token = User::factory()->create()->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->get('/api/v1/_test/protected')
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});
