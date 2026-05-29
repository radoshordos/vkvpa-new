<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vykreslí e-mailovou adresu jako PNG (anti-scraping) – nahrazuje mail.php.
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
        $bg = imagecolorallocate($im, 255, 255, 255);
        $fg = imagecolorallocate($im, 0, 0, 0);
        imagestring($im, 4, 0, 0, $text, $fg);

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
