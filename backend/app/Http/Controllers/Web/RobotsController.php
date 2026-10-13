<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Paths crawlers should skip. This only keeps them out of search results;
     * the pages themselves are protected by authentication.
     *
     * @var list<string>
     */
    public const array DISALLOWED_PATHS = ['/admin', '/seller', '/keranjang', '/checkout', '/pesanan', '/akun'];

    /**
     * robots.txt, served from a route so the sitemap URL follows APP_URL.
     */
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            ...array_map(fn (string $path): string => "Disallow: {$path}", self::DISALLOWED_PATHS),
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
