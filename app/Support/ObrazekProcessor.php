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

    /**
     * Maximální počet pixelů vstupního obrázku (šířka × výška). Brání útoku
     * typu „decompression bomb" – malý komprimovaný soubor s obrovskými rozměry,
     * který by při dekódování vyčerpal paměť. 50 Mpx pokryje i profi fotoaparáty.
     */
    private const MAX_PIXELU = 50_000_000;

    public function __construct(private readonly ImageManager $manager) {}

    /**
     * Vytvoří instanci s nejlepším dostupným ovladačem: Imagick (kvůli HEIC),
     * jinak GD.
     */
    public static function create(): self
    {
        if (extension_loaded('imagick')) {
            self::omezImagickZdroje();

            return new self(new ImageManager(new ImagickDriver));
        }

        return new self(new ImageManager(new GdDriver));
    }

    /**
     * Tvrdě omezí zdroje, které smí ImageMagick spotřebovat při dekódování
     * (zejm. HEIC/HEIF). Doplňuje kontrolu rozměrů a chrání před bombami
     * i u formátů, jejichž rozměry nelze levně zjistit přes getimagesize().
     */
    private static function omezImagickZdroje(): void
    {
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024); // 256 MB RAM
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);    // 512 MB mmap
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_AREA, 128 * 1024 * 1024);   // pixel cache
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_DISK, 1024 * 1024 * 1024);  // 1 GB swap
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_TIME, 30);                  // 30 s
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
        $this->overRozmery($cesta);

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

    /**
     * Odmítne obrázky s nereálně velkým rozlišením ještě před dekódováním
     * (decompression bomb). Rozměry zjišťuje levně z hlavičky přes getimagesize;
     * formáty, které getimagesize neumí (HEIC/HEIF), kryjí Imagick limity
     * nastavené v {@see omezImagickZdroje()}.
     *
     * @throws RuntimeException pokud obrázek překračuje povolený počet pixelů
     */
    private function overRozmery(string $cesta): void
    {
        $info = @getimagesize($cesta);
        if (is_array($info) && $info[0] * $info[1] > self::MAX_PIXELU) {
            throw new RuntimeException('Obrázek má příliš velké rozlišení.');
        }
    }
}
