<?php

declare(strict_types=1);

namespace App\Support;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

/**
 * Zpracování nahraných fotografií pro diskuzi.
 *
 * Z nahraného souboru vyrobí zmenšený hlavní obrázek + čtvercový náhled,
 * odstraní EXIF (vč. GPS) a sjednotí výstupní formát. HEIC/HEIF z mobilů
 * dekóduje přes Imagick (pokud je k dispozici); ostatní rastrové formáty
 * zvládne i GD.
 */
final class ObrazekProcessor
{
    /** Delší strana hlavního obrázku v px. */
    private const MAX_HRANA = 2000;

    /** Strana čtvercového náhledu v px. */
    private const NAHLED_HRANA = 400;

    /** Kvalita JPEG výstupu. */
    private const JPEG_KVALITA = 82;

    public function __construct(private readonly ImageManager $manager) {}

    /**
     * Vytvoří instanci s nejlepším dostupným ovladačem: Imagick (kvůli HEIC),
     * jinak GD.
     */
    public static function create(): self
    {
        $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;

        return new self(new ImageManager($driver));
    }

    public static function imagickKDispozici(): bool
    {
        return extension_loaded('imagick');
    }

    /**
     * Zpracuje soubor a vrátí data připravená pro uložení do `diskuse_foto`.
     *
     * @return array{mime: string, data: string, nahled: string, sirka: int, vyska: int, velikost: int}
     *
     * @throws RuntimeException pokud soubor nelze dekódovat jako obrázek
     */
    public function zpracuj(string $cesta): array
    {
        // PNG si necháme jako PNG kvůli průhlednosti, vše ostatní → JPEG.
        $jePng = $this->jePng($cesta);

        // Zdroj načteme zvlášť pro hlavní obrázek a pro náhled – Intervention
        // image je mutable a klonování by mohlo sdílet podkladový resource.
        $hlavni = $this->nacti($cesta)->scaleDown(self::MAX_HRANA, self::MAX_HRANA);
        $nahled = $this->nacti($cesta)->coverDown(self::NAHLED_HRANA, self::NAHLED_HRANA);

        if ($jePng) {
            $dataHlavni = $hlavni->encode(new PngEncoder)->toString();
            $dataNahled = $nahled->encode(new PngEncoder)->toString();
            $mime = 'image/png';
        } else {
            $dataHlavni = $hlavni->encode(new JpegEncoder(quality: self::JPEG_KVALITA))->toString();
            $dataNahled = $nahled->encode(new JpegEncoder(quality: self::JPEG_KVALITA))->toString();
            $mime = 'image/jpeg';
        }

        return [
            'mime' => $mime,
            'data' => $dataHlavni,
            'nahled' => $dataNahled,
            'sirka' => $hlavni->width(),
            'vyska' => $hlavni->height(),
            'velikost' => strlen($dataHlavni),
        ];
    }

    /**
     * Načte obrázek a srovná orientaci podle EXIF (mobily fotí „na výšku"
     * přes metadata).
     *
     * @throws RuntimeException pokud soubor nelze dekódovat jako obrázek
     */
    private function nacti(string $cesta): ImageInterface
    {
        try {
            return $this->manager->decodePath($cesta)->orient();
        } catch (Throwable $e) {
            throw new RuntimeException('Soubor se nepodařilo načíst jako obrázek.', 0, $e);
        }
    }

    private function jePng(string $cesta): bool
    {
        // HEIC apod. getimagesize nezná (vrátí false) → rozhodně to není PNG.
        $info = @getimagesize($cesta);

        return is_array($info) && $info[2] === IMAGETYPE_PNG;
    }
}
