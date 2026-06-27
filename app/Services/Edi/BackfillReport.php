<?php

declare(strict_types=1);

namespace App\Services\Edi;

/**
 * Výsledek běhu {@see EdiheadCategoryBackfiller}: kolik řádků se zařadilo,
 * kolik zůstalo nezařazených a kolik narazilo na nesoulad s číselníkem.
 */
final class BackfillReport
{
    /** Celkem zpracovaných řádků `edi_head`. */
    public int $total = 0;

    /** Řádků s platnou kategorií, jejíž band/section sedí na p_band/p_sect. */
    public int $resolved = 0;

    /** Řádků, kterým se reálně změnila hodnota edi_category_id (zápis). */
    public int $changed = 0;

    /** Řádků, jejichž pásmo/sekci nelze rozpoznat → zůstávají NULL. */
    public int $unresolved = 0;

    /** Řádků, kde zařazená kategorie nesedí na p_band/p_sect (rozbitý číselník). */
    public int $mismatched = 0;

    /**
     * Ukázky nesouladů: "p_band | p_sect" → id prvního takového řádku.
     *
     * @var array<string, int>
     */
    public array $mismatchSample = [];
}
