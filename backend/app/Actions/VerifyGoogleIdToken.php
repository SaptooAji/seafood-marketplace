<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\GoogleProfile;
use App\Exceptions\GoogleUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verifies a Google ID token sent by the Android app using Google's tokeninfo endpoint.
 */
class VerifyGoogleIdToken
{
    private const string TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    /** @var list<string> */
    private const array ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * Return the profile in the token once Google confirms it and its claims
     * pass: an allowed audience, a Google issuer, not expired, and a verified email.
     *
     * @throws ValidationException on the `id_token` field when the token is rejected
     * @throws GoogleUnavailable when Google cannot be reached or answers with a server error
     */
    public function handle(string $idToken): GoogleProfile
    {
        try {
            $response = Http::timeout(5)->get(self::TOKENINFO_URL, ['id_token' => $idToken]);
        } catch (ConnectionException $e) {
            throw new GoogleUnavailable($e);
        }

        if ($response->serverError()) {
            throw new GoogleUnavailable;
        }

        /** @var array<string, string> $claims tokeninfo returns every claim as a string */
        $claims = $response->successful() ? (array) $response->json() : [];

        if (! $this->claimsAreValid($claims)) {
            throw ValidationException::withMessages([
                'id_token' => 'Token Google tidak valid.',
            ]);
        }

        return new GoogleProfile(
            id: $claims['sub'],
            email: $claims['email'],
            name: $claims['name'] ?? Str::before($claims['email'], '@'),
            avatar: $claims['picture'] ?? null,
            emailVerified: true,
        );
    }

    /**
     * @param  array<string, string>  $claims
     */
    private function claimsAreValid(array $claims): bool
    {
        return isset($claims['sub'], $claims['email'], $claims['aud'], $claims['iss'], $claims['exp'])
            && in_array($claims['aud'], config()->array('services.google.allowed_client_ids'), true)
            && in_array($claims['iss'], self::ISSUERS, true)
            && (int) $claims['exp'] > now()->timestamp
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
    }
}
