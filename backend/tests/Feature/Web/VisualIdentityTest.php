<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/*
 * The visual identity "Bakau dan Kepiting" (#166): colour and font tokens in
 * resources/css/app.css, kept in sync with design/tokens.json, the Android
 * colours, and docs/ui-guidelines.md; the logo, the icons, and the manifest.
 * Pages use the role tokens, not Tailwind's own palette.
 */

/**
 * Tailwind palettes the buyer pages may no longer use directly.
 */
const RAW_PALETTES = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

beforeEach(function () {
    config(['app.name' => 'Seafood Marketplace']);
});

/**
 * The colour tokens in the Tailwind theme, name => uppercase hex.
 *
 * @return array<string, string>
 */
function themeColors(): array
{
    preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9A-Fa-f]{6});/', (string) file_get_contents(resource_path('css/app.css')), $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn (array $match) => [$match[1] => strtoupper($match[2])])->all();
}

/**
 * A file of the monorepo outside backend/, such as design/tokens.json.
 */
function repoFile(string $path): string
{
    return (string) file_get_contents(base_path('../'.$path));
}

/**
 * WCAG 2 contrast ratio between two hex colours.
 */
function contrastRatio(string $foreground, string $background): float
{
    $luminance = function (string $hex): float {
        $channels = array_map(
            function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };

    $lighter = max($luminance($foreground), $luminance($background));
    $darker = min($luminance($foreground), $luminance($background));

    return ($lighter + 0.05) / ($darker + 0.05);
}

describe('tokens', function () {
    it('defines every colour role', function () {
        expect(array_keys(themeColors()))->toContain(
            'primary-50', 'primary-100', 'primary-600', 'primary-700', 'primary-800',
            'accent-50', 'accent-500', 'accent-700',
            'ink', 'muted', 'surface', 'subtle', 'line', 'line-strong', 'pattern',
            'success-50', 'success-700', 'success-800', 'warning-50', 'warning-200', 'warning-700', 'danger-50', 'danger-700',
        );
    });

    it('uses mangrove green as primary, crab orange as accent, and sand as the page', function () {
        expect(themeColors())
            ->toMatchArray(['primary-700' => '#2F5D46', 'accent-500' => '#D9733C', 'surface' => '#F6F3EC', 'ink' => '#1B2E25']);
    });

    it('matches design/tokens.json', function () {
        $tokens = collect(json_decode(repoFile('design/tokens.json'), true, flags: JSON_THROW_ON_ERROR)['color'])
            ->map(fn (array $token) => strtoupper($token['$value']))
            ->all();

        expect($tokens)->toEqual(themeColors());
    });

    it('documents the same colours in docs/ui-guidelines.md', function () {
        preg_match_all('/^\| `([a-z0-9-]+)` \| `(#[0-9A-F]{6})` \|/m', repoFile('docs/ui-guidelines.md'), $matches, PREG_SET_ORDER);
        $documented = collect($matches)->mapWithKeys(fn (array $match) => [$match[1] => $match[2]])->all();

        expect($documented)->toEqual(themeColors());
    });

    it('gives the Android app the same colours', function () {
        preg_match_all('/<color name="brand_([a-z0-9_]+)">#FF([0-9A-F]{6})<\/color>/', repoFile('design/android/res/values/colors.xml'), $matches, PREG_SET_ORDER);
        $android = collect($matches)->mapWithKeys(fn (array $match) => [str_replace('_', '-', $match[1]) => '#'.$match[2]])->all();

        // Status colours are aliases on the web; Android uses the Material roles in Theme.kt instead.
        $web = collect(themeColors())->reject(fn (string $hex, string $name) => Str::startsWith($name, ['success-', 'warning-']))->all();

        expect($android)->toEqual($web);
    });

    it('keeps text readable on the colours it is used on (WCAG AA)', function (string $foreground, string $background, float $minimum) {
        $colors = [...themeColors(), 'white' => '#FFFFFF'];

        expect(contrastRatio($colors[$foreground], $colors[$background]))->toBeGreaterThanOrEqual($minimum);
    })->with([
        'body text on white' => ['ink', 'white', 4.5],
        'body text on the sand page' => ['ink', 'surface', 4.5],
        'secondary text on white' => ['muted', 'white', 4.5],
        'secondary text on the sand page' => ['muted', 'surface', 4.5],
        'secondary text on a placeholder' => ['muted', 'subtle', 4.5],
        'links on white' => ['primary-700', 'white', 4.5],
        'links on the sand page' => ['primary-700', 'surface', 4.5],
        'primary button' => ['white', 'primary-700', 4.5],
        'primary button pressed' => ['white', 'primary-800', 4.5],
        'active chip and stock time label' => ['white', 'ink', 4.5],
        'white button on the photo hero' => ['primary-800', 'white', 4.5],
        'ready to ship label' => ['primary-800', 'primary-50', 4.5],
        'stock not confirmed label' => ['accent-700', 'accent-50', 4.5],
        'WhatsApp button' => ['white', 'success-700', 4.5],
        'error text on white' => ['danger-700', 'white', 4.5],
        'out of stock label' => ['danger-700', 'danger-50', 4.5],
        // WCAG 1.4.11: the edge of a form field needs 3:1.
        'form field border on white' => ['line-strong', 'white', 3.0],
        'form field border on the sand page' => ['line-strong', 'surface', 3.0],
    ]);

    it('loads Schibsted Grotesk through Vite, from this site', function () {
        preg_match("/--font-sans:\s*'([^']+)'/", (string) file_get_contents(resource_path('css/app.css')), $match);

        expect($match[1] ?? null)->toBe('Schibsted Grotesk')
            ->and((string) file_get_contents(base_path('vite.config.js')))->toContain("bunny('Schibsted Grotesk'")
            ->and(json_decode(repoFile('design/tokens.json'), true)['font']['family']['$value'])->toBe('Schibsted Grotesk');
    });

    it('keeps Tailwind palettes out of the buyer pages', function () {
        $files = collect(File::allFiles(resource_path('views')))
            ->reject(fn (SplFileInfo $file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'filament'.DIRECTORY_SEPARATOR))
            ->mapWithKeys(fn (SplFileInfo $file) => [$file->getRelativePathname() => (string) file_get_contents($file->getPathname())]);

        $offenders = $files
            ->map(fn (string $content) => preg_match_all('/(?<![\w-])(?:[\w\[\]:-]+:)?(?:bg|text|border|ring|outline|fill|stroke|divide|placeholder|decoration|from|via|to)-(?:'.RAW_PALETTES.')-\d{2,3}\b/', $content, $matches) ? $matches[0] : [])
            ->filter()
            ->all();

        expect($offenders)->toBe([]);
    });
});

describe('logo and icons', function () {
    it('links the icons, the manifest, and the theme colour from the layout', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" sizes="48x48">', escape: false)
            ->assertSee('<link rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml">', escape: false)
            ->assertSee('<link rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'">', escape: false)
            ->assertSee('<link rel="manifest" href="'.asset('site.webmanifest').'">', escape: false)
            ->assertSee('<meta name="theme-color" content="'.themeColors()['primary-700'].'">', escape: false);
    });

    it('shows the logo mark beside the app name in the header, hidden from screen readers', function () {
        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->toMatch('#<a href="'.preg_quote(url('/'), '#').'"[^>]*>\s*<svg[^>]*aria-hidden="true"[^>]*>.*?</svg>\s*<span[^>]*>Seafood Marketplace</span>#s');
    });

    it('ships the logo mark from design/logo as the SVG favicon', function () {
        $svg = simplexml_load_file(public_path('favicon.svg'));

        expect($svg)->not->toBeFalse()
            ->and($svg->getName())->toBe('svg')
            ->and((string) $svg['viewBox'])->toBe('0 0 64 64')
            ->and(file_get_contents(public_path('favicon.svg')))->toBe(repoFile('design/logo/logo-mark.svg'))
            ->and(strtoupper((string) file_get_contents(public_path('favicon.svg'))))->toContain(themeColors()['ink'], themeColors()['accent-500']);
    });

    it('ships favicon.ico with 16, 32, and 48 pixel images', function () {
        $ico = (string) file_get_contents(public_path('favicon.ico'));
        ['reserved' => $reserved, 'type' => $type, 'count' => $count] = unpack('vreserved/vtype/vcount', $ico);

        $sizes = collect(range(0, $count - 1))
            ->map(fn (int $index) => ord($ico[6 + $index * 16]))
            ->all();

        expect([$reserved, $type])->toBe([0, 1])
            ->and($sizes)->toBe([16, 32, 48]);
    });

    it('ships the square PNG icons at their sizes', function (string $file, int $size) {
        $info = getimagesize(public_path($file));

        expect($info)->not->toBeFalse()
            ->and([$info[0], $info[1], $info[2]])->toBe([$size, $size, IMAGETYPE_PNG]);
    })->with([
        'apple-touch-icon' => ['apple-touch-icon.png', 180],
        'Android Chrome 192' => ['android-chrome-192.png', 192],
        'Android Chrome 512' => ['android-chrome-512.png', 512],
    ]);

    it('describes the site in a web manifest with the brand colours and icons', function () {
        $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        expect($manifest)
            ->toMatchArray(['name' => 'Seafood Marketplace', 'lang' => 'id', 'start_url' => '/'])
            ->and($manifest['theme_color'])->toBe(themeColors()['primary-700'])
            ->and($manifest['background_color'])->toBe(themeColors()['surface'])
            ->and(collect($manifest['icons'])->pluck('src')->all())->toBe(['/android-chrome-192.png', '/android-chrome-512.png']);

        foreach ($manifest['icons'] as $icon) {
            expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
        }
    });

    it('ships the optional sea pattern from design/pattern in the pattern colour', function () {
        expect(file_get_contents(public_path('images/pola-laut.svg')))
            ->toBe(repoFile('design/pattern/pola-laut.svg'))
            ->toContain('stroke="'.themeColors()['pattern'].'"');
    });
});

it('loads fonts, styles, scripts, and icons only from this site', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    preg_match_all('/<(?:script[^>]+src|link[^>]+href)="(https?:\/\/[^"]+)"/', $html, $matches);
    preg_match_all('/<link[^>]+rel="canonical"[^>]+href="([^"]+)"/', $html, $canonical);

    $external = collect($matches[1])
        ->diff($canonical[1])
        ->reject(fn (string $asset) => str_starts_with($asset, url('/')))
        ->values()
        ->all();

    expect($external)->toBe([])
        ->and($html)->not->toContain('fonts.googleapis.com', 'fonts.gstatic.com', 'fonts.bunny.net');
})->with(['home' => '/', 'catalog' => '/produk']);
