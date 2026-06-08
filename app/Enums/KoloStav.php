<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\VkvpaKola;

/**
 * Fáze životního cyklu závodního kola.
 *
 * Stav se odvozuje ze sloupců {@see VkvpaKola}: `aktivni`, `vyhodnoceno`,
 * `datum_konani` a `datum_uzaverky` (viz {@see VkvpaKola::stav()}).
 * Posloupnost odpovídá tomu, jak kolem prochází naplánované úlohy a admin:
 *
 *  1) {@see self::Nadchazejici} – kolo je založené, ale den závodu (08:00 UTC)
 *     ještě nenastal (`kola:ensure-upcoming` zakládá kola s `aktivni = false`).
 *  2) {@see self::Aktivni} – probíhá příjem hlášení (`aktivni = true`); kolo
 *     aktivuje `kola:activate-due` v 08:00 UTC v den závodu.
 *  3) {@see self::Prijem} – den závodu už proběhl, kolo není označené jako
 *     aktivní, ale uzávěrka ještě neuplynula → hlášení se stále přijímají.
 *  4) {@see self::Uzavrene} – uzávěrka uplynula, kolo už nepřijímá hlášení
 *     a probíhá zpracování výsledků (ještě není `vyhodnoceno`). Fáze je krátká.
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
