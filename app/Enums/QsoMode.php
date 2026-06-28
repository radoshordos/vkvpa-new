<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Druh provozu spojení (EDI sloupec „Mode-code").
 *
 * Ve VKV PA jsou oficiálně povolené pouze kódy 1–6 standardu REG1TEST
 * (IARU Region 1): SSB, CW, oba směry křížového provozu, AM a FM. Každý z nich
 * má ve vizualizaci vlastní kontrastní barvu. Jakýkoli jiný kód – ať už další
 * REG1TEST mód (MGM/SSTV/ATV) nebo rozhozený sloupec v deníku, kam se vlilo RST
 * (`59`, `599` …) – se mapuje na {@see self::Other} („Ostatní", v mapě šedá,
 * popisek „?"). Hodnota (`value`) putuje i do JSON pro barvení markerů.
 */
enum QsoMode: int
{
    case Other = 0;
    case Ssb = 1;
    case Cw = 2;
    case SsbCw = 3; // křížový provoz: vysílá SSB, přijímá CW
    case CwSsb = 4; // křížový provoz: vysílá CW, přijímá SSB
    case Am = 5;
    case Fm = 6;

    /**
     * Mapuje kód z deníku na enum; cokoli mimo povolené 1–6 → {@see self::Other}.
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
            self::SsbCw => 'SSB/CW',
            self::CwSsb => 'CW/SSB',
            self::Am => 'AM',
            self::Fm => 'FM',
            self::Other => '?',
        };
    }
}
