<?php

declare(strict_types=1);

/*
 * Draws the PNG and ICO icons from the logo mark in design/logo/*.svg with GD,
 * so no SVG rasterizer is needed. Run from the repository root after changing
 * the mark or the colors:
 *
 *     php design/scripts/generate-icons.php
 *
 * Writes the web icons to backend/public and the Android launcher icons to
 * design/android/res. The shapes below mirror logo-mark.svg (a 64 unit grid).
 */

const INK = '#1B2E25';
const WHITE = '#FFFFFF';
const CRAB = '#D9733C';
const MANGROVE = '#2F5D46';
const SUPERSAMPLE = 8;

$root = dirname(__DIR__, 2);

function color(GdImage $image, string $hex): int
{
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

    return imagecolorallocate($image, $r, $g, $b);
}

function canvas(int $size): GdImage
{
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagealphablending($image, true);

    return $image;
}

function roundedRect(GdImage $image, float $x, float $y, float $w, float $h, float $r, int $color): void
{
    imagefilledrectangle($image, (int) round($x + $r), (int) round($y), (int) round($x + $w - $r), (int) round($y + $h), $color);
    imagefilledrectangle($image, (int) round($x), (int) round($y + $r), (int) round($x + $w), (int) round($y + $h - $r), $color);
    foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
        imagefilledellipse($image, (int) round($cx), (int) round($cy), (int) round(2 * $r), (int) round(2 * $r), $color);
    }
}

/**
 * Points along the fish outline of logo-mark.svg, in mark units.
 *
 * @return list<array{float, float}>
 */
function fishOutline(): array
{
    $cubic = function (array $p0, array $p1, array $p2, array $p3): array {
        $points = [];
        for ($i = 1; $i <= 24; $i++) {
            $t = $i / 24;
            $u = 1 - $t;
            $points[] = [
                $u ** 3 * $p0[0] + 3 * $u ** 2 * $t * $p1[0] + 3 * $u * $t ** 2 * $p2[0] + $t ** 3 * $p3[0],
                $u ** 3 * $p0[1] + 3 * $u ** 2 * $t * $p1[1] + 3 * $u * $t ** 2 * $p2[1] + $t ** 3 * $p3[1],
            ];
        }

        return $points;
    };

    return [
        [14, 33],
        ...$cubic([14, 33], [19, 26], [26, 24], [34, 26]),
        [41, 21],
        [41, 45],
        [34, 40],
        ...$cubic([34, 40], [26, 42], [19, 40], [14, 33]),
    ];
}

/**
 * Draws the mark with its top-left corner at ($x, $y), one mark unit being $u pixels.
 * With $ring the white square has the ink outline of logo-mark.svg; without it
 * (on a colored icon background) it is a plain white square.
 */
function drawMark(GdImage $image, float $x, float $y, float $u, bool $ring): void
{
    $ink = color($image, INK);
    if ($ring) {
        roundedRect($image, $x + 2.5 * $u, $y + 2.5 * $u, 59 * $u, 59 * $u, 19.5 * $u, $ink);
        roundedRect($image, $x + 5.5 * $u, $y + 5.5 * $u, 53 * $u, 53 * $u, 16.5 * $u, color($image, WHITE));
    } else {
        roundedRect($image, $x + 4 * $u, $y + 4 * $u, 56 * $u, 56 * $u, 18 * $u, color($image, WHITE));
    }

    $points = fishOutline();
    $thickness = 3 * $u;
    imagesetthickness($image, (int) max(1, round($thickness)));
    for ($i = 0, $n = count($points); $i < $n; $i++) {
        [$ax, $ay] = $points[$i];
        [$bx, $by] = $points[($i + 1) % $n];
        imageline($image, (int) round($x + $ax * $u), (int) round($y + $ay * $u), (int) round($x + $bx * $u), (int) round($y + $by * $u), $ink);
        imagefilledellipse($image, (int) round($x + $ax * $u), (int) round($y + $ay * $u), (int) round($thickness), (int) round($thickness), $ink);
    }
    imagesetthickness($image, 1);

    imagefilledellipse($image, (int) round($x + 48 * $u), (int) round($y + 15 * $u), (int) round(12 * $u), (int) round(12 * $u), color($image, CRAB));
}

/**
 * Renders one icon at $size pixels.
 *
 * mark:    the outlined mark on transparency (favicon)
 * square:  full-bleed mangrove background (apple-touch-icon, web manifest, Play Store)
 * rounded: mangrove rounded square (legacy Android launcher)
 * circle:  mangrove circle (legacy round Android launcher)
 */
function render(int $size, string $style): GdImage
{
    // Drawn larger and scaled down for smooth edges; at most about 2048 pixels wide.
    $big = $size * max(2, min(SUPERSAMPLE, intdiv(2048, $size)));
    $image = canvas($big);
    $mangrove = color($image, MANGROVE);

    match ($style) {
        'square' => imagefilledrectangle($image, 0, 0, $big, $big, $mangrove),
        'rounded' => roundedRect($image, $big * 0.04, $big * 0.04, $big * 0.92, $big * 0.92, $big * 0.2, $mangrove),
        'circle' => imagefilledellipse($image, intdiv($big, 2), intdiv($big, 2), (int) ($big * 0.92), (int) ($big * 0.92), $mangrove),
        default => null,
    };

    // The mark fills the favicon and about 56 to 60 percent of a launcher icon.
    $markSize = $style === 'mark' ? $big : $big * ($style === 'circle' ? 0.56 : 0.6);
    drawMark($image, ($big - $markSize) / 2, ($big - $markSize) / 2, $markSize / 64, $style === 'mark');

    $icon = canvas($size);
    imagealphablending($icon, false);
    imagecopyresampled($icon, $image, 0, 0, 0, 0, $size, $size, $big, $big);

    return $icon;
}

function png(GdImage $image): string
{
    ob_start();
    imagepng($image, null, 9);

    return (string) ob_get_clean();
}

function write(string $path, string $contents): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
    echo 'wrote '.$path.PHP_EOL;
}

// Web: favicon.ico with 16, 32, and 48 pixel PNG entries.
$sizes = [16, 32, 48];
$images = array_map(fn (int $size) => png(render($size, 'mark')), $sizes);
$ico = pack('vvv', 0, 1, count($sizes));
$offset = 6 + 16 * count($sizes);
foreach ($sizes as $i => $size) {
    $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($images[$i]), $offset);
    $offset += strlen($images[$i]);
}
write($root.'/backend/public/favicon.ico', $ico.implode('', $images));
write($root.'/backend/public/apple-touch-icon.png', png(render(180, 'square')));
write($root.'/backend/public/android-chrome-192.png', png(render(192, 'square')));
write($root.'/backend/public/android-chrome-512.png', png(render(512, 'square')));

// Android: legacy launcher icons for devices before adaptive icons, and the Play Store icon.
foreach (['mdpi' => 48, 'hdpi' => 72, 'xhdpi' => 96, 'xxhdpi' => 144, 'xxxhdpi' => 192] as $density => $size) {
    write($root."/design/android/res/mipmap-{$density}/ic_launcher.png", png(render($size, 'rounded')));
    write($root."/design/android/res/mipmap-{$density}/ic_launcher_round.png", png(render($size, 'circle')));
}
write($root.'/design/android/playstore-icon.png', png(render(512, 'square')));
