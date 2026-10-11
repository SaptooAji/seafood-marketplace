<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\LoginWithGoogle;
use App\Data\GoogleProfile;
use App\Exceptions\UnverifiedSocialEmail;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleLoginController extends Controller
{
    /**
     * Send the guest to the Google consent screen. Named `login` so the auth
     * middleware redirects web guests here.
     */
    public function redirectToGoogle(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Sign the user in after Google redirects back, then return them to the
     * page they wanted. Cancellations and failures go to the home page with
     * an `error` flash message.
     */
    public function handleGoogleCallback(LoginWithGoogle $loginWithGoogle): RedirectResponse
    {
        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->user();

            $user = $loginWithGoogle->handle(new GoogleProfile(
                id: (string) $googleUser->getId(),
                email: (string) $googleUser->getEmail(),
                name: (string) $googleUser->getName(),
                avatar: $googleUser->getAvatar(),
                emailVerified: (bool) ($googleUser->getRaw()['email_verified'] ?? false),
            ));
        } catch (InvalidStateException) {
            return redirect('/')->with('error', 'Login dengan Google dibatalkan atau sesinya kedaluwarsa. Silakan coba lagi.');
        } catch (UnverifiedSocialEmail $e) {
            return redirect('/')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect('/')->with('error', 'Terjadi kesalahan saat login dengan Google. Silakan coba lagi.');
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }
}
