<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\VkvpaKola;

/**
 * Fáze životního cyklu závodního kola.
 *
 * Stav se odvozuje ze sloupců {@see VkvpaKola}: `aktivni`,
 * `vyhodnoceno` a `datum_uzaverky` (viz {@see VkvpaKola::stav()}).
 * Posloupnost odpovídá tomu, jak kolem prochází naplánované úlohy a admin:
 *
 *  1) {@see self::Nadchazejici} – kolo je založené, ale den závodu (08:00 UTC)
 *     ještě nenastal (`kola:ensure-upcoming` zakládá kola s `aktivni = false`).
 *  2) {@see self::Aktivni} – probíhá příjem hlášení (`aktivni = true`); kolo
 *     aktivuje `kola:activate-due` v 08:00 UTC v den závodu.
 *  3) {@see self::Uzavrene} – uzávěrka uplynula, kolo už nepřijímá hlášení
 *     (`aktivni = false`), ale ještě není vyhodnocené.
 *  4) {@see self::Vyhodnocene} – kolo je vyhodnocené a obodované
 *     (`vyhodnoceno` je vyplněné).
 */
enum KoloStav: string
{
    case Nadchazejici = 'nadchazejici';
    case Aktivni = 'aktivni';
    case Uzavrene = 'uzavrene';
    case Vyhodnocene = 'vyhodnocene';

    /**
     * Krátký popisek stavu pro zobrazení uživateli.
     */
    public function label(): string
    {
        return match ($this) {
            self::Nadchazejici => 'Nadcházející',
            self::Aktivni => 'Probíhá',
            self::Uzavrene => 'Uzavřené',
            self::Vyhodnocene => 'Vyhodnocené',
        };
    }
}
