<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\AuthenticateWithPassword;
use App\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Email and password authentication for the Android app using Sanctum tokens.
 */
class AuthController extends Controller
{
    /**
     * Register a user and issue a token for the requesting device.
     */
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->safe()->only(['name', 'email', 'phone', 'password']));

        return $this->tokenResponse($user, $request->string('device_name')->toString(), Response::HTTP_CREATED);
    }

    /**
     * Issue a new token for the requesting device when the credentials match.
     */
    public function login(LoginRequest $request, AuthenticateWithPassword $authenticate): JsonResponse
    {
        $user = $authenticate->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return $this->tokenResponse($user, $request->string('device_name')->toString());
    }

    /**
     * Revoke the token used for this request; the user's other devices stay signed in.
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        $token->delete();

        return response()->noContent();
    }

    /**
     * Return the signed-in user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Build the `{token, user}` response shared by register and login.
     */
    private function tokenResponse(User $user, string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}
