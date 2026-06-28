<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KoloStav;
use App\Models\EdiRound;
use App\Services\Edi\KoloStatistiky;
use App\Support\VkvpaSettings;
use GdImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamický sdílecí náhled (Open Graph) pro detail kola: PNG 1200×630
 * s názvem kola a klíčovými čísly (stanice, QSO, body, ODX). Vykresleno
 * přes GD a přibalený font Roboto; výsledek se cachuje (binárka = string).
 */
final class OgImageController extends Controller
{
    private const W = 1200;

    private const H = 630;

    public function __construct(private readonly KoloStatistiky $statistiky) {}

    public function kolo(EdiRound $kolo): Response
    {
        abort_unless($kolo->state() === KoloStav::Vyhodnocene, 404);

        // PNG je binární; v databázové cache (utf8 sloupec `value`) by syrové
        // bajty selhaly (MySQL 1366 Incorrect string value), proto base64.
        $encoded = Cache::remember(
            sprintf('vkvpa:og:v2:%d', $kolo->id),
            VkvpaSettings::roundStationsCacheTtl(),
            fn (): string => base64_encode($this->render($kolo)),
        );

        $png = base64_decode($encoded, true);
        if ($png === false) {
            $png = $this->render($kolo);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function render(EdiRound $kolo): string
    {
        $p = $this->statistiky->prehled($kolo);

        $im = imagecreatetruecolor(self::W, self::H);
        if ($im === false) {
            abort(500);
        }

        $bg = $this->color($im, 15, 23, 42);       // slate-900
        $brand = $this->color($im, 129, 140, 248); // indigo-400
        $white = $this->color($im, 255, 255, 255);
        $muted = $this->color($im, 148, 163, 184);  // slate-400

        imagefilledrectangle($im, 0, 0, self::W, self::H, $bg);
        imagefilledrectangle($im, 0, 0, self::W, 14, $brand);

        $bold = base_path('resources/fonts/Roboto-Bold.ttf');
        $reg = base_path('resources/fonts/Roboto-Regular.ttf');

        imagettftext($im, 26, 0, 60, 100, $muted, $reg, 'VKV PROVOZNÍ AKTIV · STATISTIKY');
        imagettftext($im, 120, 0, 56, 250, $white, $bold, $kolo->name);
        imagettftext($im, 38, 0, 60, 320, $brand, $bold, 'Statistiky závodu');

        // Čtyři klíčová čísla v rovnoměrných sloupcích (menší font, ať se
        // velká čísla jako „534 522" nepřekrývají s dalším sloupcem).
        $cols = [
            [55, $this->num($p['pocetStanic']), 'STANIC'],
            [340, $this->num($p['pocetQso']), 'QSO'],
            [625, $this->num($p['bodyCelkem']), 'BODŮ'],
            [910, $this->num($p['odx']['dist'] ?? 0).' km', 'ODX'],
        ];
        foreach ($cols as [$x, $value, $label]) {
            imagettftext($im, 42, 0, $x, 495, $white, $bold, $value);
            imagettftext($im, 22, 0, $x, 540, $muted, $reg, $label);
        }

        imagettftext($im, 22, 0, 60, 600, $muted, $reg, 'vkvpa.hamradio.cz');

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    /**
     * Bezpečná alokace barvy (truecolor obrázek nikdy nevrací false).
     *
     * @param  int<0, 255>  $r
     * @param  int<0, 255>  $g
     * @param  int<0, 255>  $b
     */
    private function color(GdImage $im, int $r, int $g, int $b): int
    {
        $c = imagecolorallocate($im, $r, $g, $b);

        return $c === false ? 0 : $c;
    }

    /** Číslo s mezerou jako oddělovačem tisíců (12 345). */
    private function num(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }
}
