<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs the user out of the web. The web, /seller, and /admin share the
 * `web` guard, so this ends all three; the user's API tokens (the Android
 * app) are left alone.
 */
class LogoutController extends Controller
{
    /**
     * Sign out, throw the session away, and start a new CSRF token, so the
     * old session ID and token are worthless. A guest is just sent home.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Anda sudah keluar.');
    }
}
