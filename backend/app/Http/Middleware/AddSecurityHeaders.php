<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the basic security headers (CONF-5) to every response: the buyer web,
 * the Filament panels, the API, redirects, and error pages. Registered as
 * global middleware, so a 404 for an unknown route gets them too.
 *
 * A header a response already sets on purpose is kept, so a page that has to
 * be framed later can send its own X-Frame-Options. Nothing in the request
 * can change or remove them. Content-Security-Policy and HSTS come in Tahap 7
 * (CONF-4).
 */
class AddSecurityHeaders
{
    /**
     * @var array<string, string>
     */
    public const array HEADERS = [
        // Browsers must not guess a type other than the one sent (completes VAL-6).
        'X-Content-Type-Options' => 'nosniff',
        // Other sites get only the origin, never the path or query (catalog filters, buyer location, DATA-4).
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // No page is meant to be shown inside a frame (clickjacking).
        'X-Frame-Options' => 'DENY',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
