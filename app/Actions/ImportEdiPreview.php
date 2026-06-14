<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\Scoring\EdiScore;

/**
 * Výsledek nedestruktivní validace EDI deníku ({@see ImportEdiAction::preview()}):
 * dohledané kolo a kategorie + skóre spočítané z paměti (bez zápisu do DB).
 * Slouží pro náhled při podání hlášení, než závodník stiskne „Odeslat".
 */
final readonly class ImportEdiPreview
{
    public function __construct(
        public int $idKola,
        public int $idKategorie,
        public EdiScore $score,
    ) {}
}
