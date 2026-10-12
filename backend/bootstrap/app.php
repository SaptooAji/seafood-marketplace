<?php

declare(strict_types=1);

use App\Exceptions\InvalidProductData;
use App\Exceptions\InvalidProductPhoto;
use App\Exceptions\InvalidShippingMethod;
use App\Exceptions\UserAlreadyOwnsStore;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API guests get a JSON 401 instead of a redirect, even without an Accept header.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Domain exceptions stay free of HTTP; their status codes are mapped here.
        // An expected business outcome answered with a 4xx is not an error, so
        // it is kept out of the log.
        $exceptions->dontReport([UserAlreadyOwnsStore::class]);

        $exceptions->render(fn (UserAlreadyOwnsStore $e) => response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_CONFLICT,
        ));

        // Reported like any other validation error: 422 with errors.<field>.
        $exceptions->map(InvalidShippingMethod::class, fn (InvalidShippingMethod $e) => ValidationException::withMessages([
            $e->field => $e->getMessage(),
        ]));
        $exceptions->map(InvalidProductData::class, fn (InvalidProductData $e) => ValidationException::withMessages([
            $e->field => $e->getMessage(),
        ]));
        $exceptions->map(InvalidProductPhoto::class, fn (InvalidProductPhoto $e) => ValidationException::withMessages([
            $e->field => $e->getMessage(),
        ]));
    })->create();
