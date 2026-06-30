<?php

declare(strict_types=1);

namespace App\Support;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

/**
 * Zpracování nahraných fotografií pro diskuzi.
 *
 * Z nahraného souboru vyrobí zmenšený hlavní obrázek + náhled (oba se
 * zachovaným poměrem stran), odstraní EXIF (vč. GPS) a sjednotí výstup na
 * WebP – menší soubory při zachované kvalitě i průhlednosti. HEIC/HEIF
 * z mobilů dekóduje přes Imagick (pokud je k dispozici); ostatní rastrové
 * formáty zvládne i GD.
 */
final class ObrazekProcessor
{
    /** Delší strana hlavního obrázku v px. */
    private const MAX_HRANA = 2000;

    /** Delší strana náhledu v px (poměr stran se zachovává). */
    private const NAHLED_HRANA = 640;

    /** Kvalita WebP hlavního obrázku. */
    private const KVALITA = 82;

    /** Kvalita WebP náhledu. */
    private const NAHLED_KVALITA = 80;

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
     * Zpracuje soubor a vrátí data připravená pro uložení do
     * `discussion_post_photos`.
     *
     * @return array{mime_type: string, data: string, thumbnail: string, width: int, height: int, size_bytes: int}
     *
     * @throws RuntimeException pokud soubor nelze dekódovat jako obrázek
     */
    public function zpracuj(string $cesta): array
    {
        // Zdroj načteme zvlášť pro hlavní obrázek a pro náhled – Intervention
        // image je mutable a klonování by mohlo sdílet podkladový resource.
        // Oba zmenšujeme přes scaleDown, takže si zachovají poměr stran (náhled
        // tedy má stejný poměr jako hlavní obrázek – mřížka to využívá pro
        // layout bez „skákání" při lazy-loadu).
        $hlavni = $this->nacti($cesta)->scaleDown(self::MAX_HRANA, self::MAX_HRANA);
        $nahled = $this->nacti($cesta)->scaleDown(self::NAHLED_HRANA, self::NAHLED_HRANA);

        $dataHlavni = $hlavni->encode(new WebpEncoder(quality: self::KVALITA))->toString();
        $dataNahled = $nahled->encode(new WebpEncoder(quality: self::NAHLED_KVALITA))->toString();

        return [
            'mime_type' => 'image/webp',
            'data' => $dataHlavni,
            'thumbnail' => $dataNahled,
            'width' => $hlavni->width(),
            'height' => $hlavni->height(),
            'size_bytes' => strlen($dataHlavni),
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
}
