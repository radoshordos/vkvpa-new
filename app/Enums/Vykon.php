<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\EdiEntry;

/**
 * Výkonová kategorie hlášení.
 *
 * Odvozuje se z příznaků `qrp`/`lp` na {@see EdiEntry} (ty zůstávají
 * zdrojem pravdy v DB) – tento enum je jednotná reprezentace pro zobrazení i logiku.
 * QRP (≤5 W) je podmnožinou LP (<100 W); plný výkon je výchozí.
 */
enum Vykon: string
{
    case Plny = 'plny';
    case Lp = 'lp';
    case Qrp = 'qrp';

    /** Odvození z dvou booleanů (qrp má přednost – QRP ⊂ LP). */
    public static function fromFlags(bool $qrp, bool $lp): self
    {
        return match (true) {
            $qrp => self::Qrp,
            $lp => self::Lp,
            default => self::Plny,
        };
    }

    /** Plný výkon = bez příznaku; QRP/LP se značí v listinách a výsledcích. */
    public function isReduced(): bool
    {
        return $this !== self::Plny;
    }

    /** Varianta odznaku pro <x-badge variant>, nebo null pro plný výkon. */
    public function badgeVariant(): ?string
    {
        return match ($this) {
            self::Qrp => 'qrp',
            self::Lp => 'lp',
            self::Plny => null,
        };
    }

    /** Krátký štítek (QRP/LP); plný výkon štítek nemá. */
    public function label(): ?string
    {
        return match ($this) {
            self::Qrp => 'QRP',
            self::Lp => 'LP',
            self::Plny => null,
        };
    }

    /**
     * Pořadí „nižšího výkonu" pro slučování (QRP > LP > plný). Když má stanice
     * v jednom měsíci víc kol, ponecháme to s nejnižším výkonem.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Qrp => 2,
            self::Lp => 1,
            self::Plny => 0,
        };
    }
}
