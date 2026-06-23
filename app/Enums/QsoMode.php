<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Druh provozu spojení (EDI sloupec „Mode-code").
 *
 * Hodnoty (`value`) odpovídají kódům standardu REG1TEST (IARU Region 1) a putují
 * i do JSON pro vizualizaci (barvení markerů). VKV PA prakticky využívá fone
 * (SSB) a CW; ostatní módy se vyskytují zřídka. Jakýkoli neznámý / chybějící kód
 * (typicky rozhozený sloupec v deníku, kam se vlilo RST apod.) se mapuje na
 * {@see self::Other} (v mapě šedá, popisek „?").
 */
enum QsoMode: int
{
    case Other = 0;
    case Ssb = 1;
    case Cw = 2;
    case Mixed = 3;
    case Am = 5;
    case Fm = 6;
    case Mgm = 7;
    case Sstv = 8;
    case Atv = 9;

    /**
     * Mapuje kód z deníku na enum; neznámý kód → {@see self::Other}.
     */
    public static function fromCode(int $code): self
    {
        return self::tryFrom($code) ?? self::Other;
    }

    /**
     * Krátký popisek druhu provozu.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ssb => 'SSB',
            self::Cw => 'CW',
            self::Mixed => 'SSB+CW',
            self::Am => 'AM',
            self::Fm => 'FM',
            self::Mgm => 'RTTY/MGM',
            self::Sstv => 'SSTV',
            self::Atv => 'ATV',
            self::Other => '?',
        };
    }
}
