<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PhotoCreditController extends Controller
{
    /**
     * The groups on the page, in order: heading id => heading.
     *
     * @var array<string, string>
     */
    public const array GROUPS = [
        'kredit-produk' => 'Produk toko contoh',
        'kredit-resep' => 'Resep',
        'kredit-lain' => 'Halaman lain',
    ];

    /**
     * The photo credits from config/photo-credits.php, grouped: photos of
     * the demo store products (seeder files), recipe photos, and photos of
     * other pages. Only files under public/ are served, so only those get a
     * small photo.
     */
    public function __invoke(): View
    {
        $credits = [];

        foreach (config()->array('photo-credits') as $credit) {
            $file = (string) $credit['file'];
            $served = Str::startsWith($file, 'public/');

            $group = match (true) {
                ! $served => 'kredit-produk',
                Str::startsWith($file, 'public/images/resep/') => 'kredit-resep',
                default => 'kredit-lain',
            };

            $credits[$group][] = [...$credit, 'image' => $served ? asset(Str::after($file, 'public/')) : null];
        }

        $groups = [];

        foreach (self::GROUPS as $id => $heading) {
            if (isset($credits[$id])) {
                $groups[$id] = ['heading' => $heading, 'credits' => $credits[$id]];
            }
        }

        return view('photo-credits', ['groups' => $groups]);
    }
}
