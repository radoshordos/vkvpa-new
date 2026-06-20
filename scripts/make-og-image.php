<?php

declare(strict_types=1);

/*
 * Jednorázový generátor náhledového obrázku pro sdílení (Open Graph / Twitter).
 * Výstup: public/og-image.png (1200×630). Spouští se ručně:
 *
 *   php scripts/make-og-image.php
 *
 * Sociální sítě (Facebook, LinkedIn, X) nevykreslí SVG, proto potřebujeme PNG.
 * Skript zkouší několik běžných cest k tučnému/běžnému fontu (Windows + DejaVu),
 * aby šel spustit i mimo Windows.
 */

$W = 1200;
$H = 630;

$boldCandidates = [
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/segoeuib.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/Library/Fonts/Arial Bold.ttf',
];
$regularCandidates = [
    'C:/Windows/Fonts/arial.ttf',
    'C:/Windows/Fonts/segoeui.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/Library/Fonts/Arial.ttf',
];

$pick = static function (array $paths): string {
    foreach ($paths as $p) {
        if (is_file($p)) {
            return $p;
        }
    }
    fwrite(STDERR, "Nenalezen žádný použitelný font.\n");
    exit(1);
};

$fontBold = $pick($boldCandidates);
$fontReg = $pick($regularCandidates);

$img = imagecreatetruecolor($W, $H);

// Pozadí – jemný svislý přechod z brand indigo do tmavší.
$top = [67, 56, 202];   // #4338ca
$bot = [49, 41, 148];   // tmavší
for ($y = 0; $y < $H; $y++) {
    $r = (int) ($top[0] + ($bot[0] - $top[0]) * $y / $H);
    $g = (int) ($top[1] + ($bot[1] - $top[1]) * $y / $H);
    $b = (int) ($top[2] + ($bot[2] - $top[2]) * $y / $H);
    $col = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $W, $y, $col);
}

$white = imagecolorallocate($img, 255, 255, 255);
$brand = imagecolorallocate($img, 67, 56, 202);
$muted = imagecolorallocate($img, 213, 216, 240);

// Bílý zaoblený odznak „PA" vlevo.
$bx = 90;
$by = 235;
$bs = 160;
$rad = 32;
imagefilledrectangle($img, $bx + $rad, $by, $bx + $bs - $rad, $by + $bs, $white);
imagefilledrectangle($img, $bx, $by + $rad, $bx + $bs, $by + $bs - $rad, $white);
foreach ([[$bx + $rad, $by + $rad], [$bx + $bs - $rad, $by + $rad], [$bx + $rad, $by + $bs - $rad], [$bx + $bs - $rad, $by + $bs - $rad]] as $c) {
    imagefilledellipse($img, $c[0], $c[1], $rad * 2, $rad * 2, $white);
}
// „PA" v odznaku.
$pa = imagettfbbox(64, 0, $fontBold, 'PA');
$paW = $pa[2] - $pa[0];
imagettftext($img, 64, 0, (int) ($bx + ($bs - $paW) / 2), $by + 108, $brand, $fontBold, 'PA');

// Titulek + podtitulek vpravo od odznaku.
$tx = $bx + $bs + 60;
imagettftext($img, 58, 0, $tx, 320, $white, $fontBold, 'VKV Provozní aktiv');
imagettftext($img, 27, 0, $tx, 380, $muted, $fontReg, 'Elektronické výsledky a hlášení');
imagettftext($img, 27, 0, $tx, 422, $muted, $fontReg, 'amatérských VKV závodů');

// Patička.
imagettftext($img, 22, 0, 90, 575, $muted, $fontReg, 'ok1vum.hamradio.cz');

$out = __DIR__.'/../public/og-image.png';
imagepng($img, $out, 6);

echo "Hotovo: {$out} (".filesize($out)." B)\n";
