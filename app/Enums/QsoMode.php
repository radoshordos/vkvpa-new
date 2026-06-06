<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Druh provozu spojení (EDI sloupec „Mode-code").
 *
 * Hodnota (`value`) odpovídá kódu z deníku a putuje i do JSON pro vizualizaci
 * (barvení markerů). VKV PA využívá fone (SSB) a CW; jakýkoli jiný / chybějící
 * kód se mapuje na {@see self::Other} (v mapě šedá, popisek „?").
 */
enum QsoMode: int
{
    case Other = 0;
    case Ssb = 1;
    case Cw = 2;

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
            self::Other => '?',
        };
    }
}
