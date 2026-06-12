<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\VkvpaKola;

/**
 * Fáze životního cyklu závodního kola.
 *
 * Stav je čistá funkce času – odvozuje se ze sloupců {@see VkvpaKola}:
 * `vyhodnoceno`, `datum_konani` (start závodu, standardně 08:00 UTC)
 * a `datum_uzaverky` (viz {@see VkvpaKola::stav()}):
 *
 *  1) {@see self::Nadchazejici} – kolo je založené, ale start závodu
 *     (`datum_konani`) ještě nenastal.
 *  2) {@see self::Aktivni} – závod právě běží (od `datum_konani` do konce
 *     závodního okna, {@see VkvpaKola::konecZavodu()}); hlášení se přijímají.
 *  3) {@see self::Prijem} – závod skončil, ale uzávěrka ještě neuplynula
 *     → hlášení se stále přijímají.
 *  4) {@see self::Uzavrene} – uzávěrka uplynula, kolo už od běžných závodníků
 *     nepřijímá hlášení a probíhá zpracování výsledků (ještě není
 *     `vyhodnoceno`). Fáze je krátká.
 *  5) {@see self::Vyhodnocene} – kolo je vyhodnocené a obodované
 *     (`vyhodnoceno` je vyplněné).
 */
enum KoloStav: string
{
    case Nadchazejici = 'nadchazejici';
    case Aktivni = 'aktivni';
    case Prijem = 'prijem';
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
            self::Prijem => 'Příjem hlášení',
            self::Uzavrene => 'Zpracování výsledků',
            self::Vyhodnocene => 'Vyhodnocené',
        };
    }

    /**
     * CSS třída badge pro barevné odlišení stavu ve výpisech.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Nadchazejici => 'badge-muted',
            self::Aktivni => 'badge-ok',
            self::Prijem => 'badge-warn',
            self::Uzavrene => 'badge-brand',
            self::Vyhodnocene => 'badge-muted',
        };
    }
}
