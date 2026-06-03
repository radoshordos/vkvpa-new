<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Support\ContestWindow;

/**
 * Ořez EDI deníku (REG1TEST) na časové okno závodu – podklad pro akci „EDIR".
 *
 * Někteří (hlavně zahraniční) účastníci posílají EDI s QSO i mimo závodní okno
 * (před 08:00 a po 11:00 UTC). Tato služba z původního EDI ponechá jen QSO
 * uvnitř okna 08:00–11:00 UTC, zachová hlavičku i sekci [Remarks] a přepočítá
 * počet v řádku `[QSORecords;N]`. Výsledek je platný (zmenší) EDI soubor.
 *
 * Čistá služba bez DB a výstupu (snadno testovatelná).
 */
final class EdiReducer
{
    /**
     * Vrátí EDI text oříznutý jen na QSO uvnitř závodního okna.
     *
     * @param  string  $raw  původní obsah EDI souboru (sloupec `edihead.src`)
     * @return string platný EDI s přepočítaným počtem QSO
     */
    public function reduce(string $raw): string
    {
        $head = [];   // vše před řádkem [QSORecords;…] (hlavička + [Remarks])
        $kept = [];   // QSO řádky uvnitř okna
        $tail = [];   // [END;] a případné řádky za ním
        $state = 'head';

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $buf = trim($line);

            if ($state === 'head') {
                if (str_starts_with($buf, '[QSORecords;')) {
                    // Řádek s počtem QSO vložíme znovu níže s aktualizovaným číslem.
                    $state = 'records';

                    continue;
                }
                $head[] = $line;

                continue;
            }

            if ($state === 'records') {
                if (str_starts_with($buf, '[END')) {
                    $state = 'tail';
                    $tail[] = $line;

                    continue;
                }
                if ($buf === '') {
                    continue;
                }
                // QSO řádek: Date;Time;Call;… – druhý sloupec je čas (HHMM).
                $time = explode(';', $buf)[1] ?? '';
                if ($this->inWindow($time)) {
                    $kept[] = $line;
                }

                continue;
            }

            $tail[] = $line;
        }

        $out = [...$head, '[QSORecords;'.count($kept).']', ...$kept, ...$tail];

        return implode("\n", $out);
    }

    /** Je čas QSO (HHMM) uvnitř závodního okna (včetně hranic)? */
    private function inWindow(string $time): bool
    {
        $hhmm = substr(trim($time), 0, 4);

        return strlen($hhmm) === 4 && $hhmm >= ContestWindow::from() && $hhmm <= ContestWindow::to();
    }
}
