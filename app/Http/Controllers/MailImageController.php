<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vykreslí e-mailovou adresu jako PNG (anti-scraping).
 * Vyžaduje rozšíření GD.
 */
class MailImageController extends Controller
{
    public function show(Request $request): Response
    {
        $text = base64_decode((string) $request->query('text'), true) ?: '';
        // Jen tisknutelné ASCII, ať nelze podstrčit nic divného.
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';

        $width = max(1, strlen($text) * 12);
        $im = imagecreate($width, 16);
        // První alokovaná barva = pozadí; uděláme ho průhledné, aby obrázek
        // splynul se světlým i tmavým motivem.
        $bg = imagecolorallocate($im, 255, 255, 255);
        if ($bg !== false) {
            imagecolortransparent($im, $bg);
        }
        // Neutrální šeď čitelná na světlém i tmavém podkladu.
        $fg = imagecolorallocate($im, 128, 134, 146);
        if ($fg === false) {
            abort(500, 'GD: nepodařilo se alokovat barvu.');
        }

        imagestring($im, 4, 0, 0, $text, $fg);

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
