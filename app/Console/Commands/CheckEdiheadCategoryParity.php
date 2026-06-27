<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Kontrola shody `edi_head.edi_category_id` ↔ `vkvpa_data.id_kategorie`.
 *
 * `edi_head.edi_category_id` je odvozený z textu EDI hlavičky (p_band/p_sect),
 * `vkvpa_data.id_kategorie` je kategorie, v níž příspěvek reálně soutěží (admin
 * ji mohl přeřadit, hlavička mohla být chybná/prázdná). U propojených řádků by
 * měly souhlasit – tento příkaz rozdíly jen REPORTUJE (nepřepisuje data),
 * ať je vidět, kde se historicky rozcházejí.
 *
 * Pozn.: osiřelé edi_head (bez vkvpa_data) se nekontrolují – nemají s čím.
 */
class CheckEdiheadCategoryParity extends Command
{
    protected $signature = 'vkvpa:check-edihead-category';

    protected $description = 'Reportuje rozdíly edi_head.edi_category_id ↔ vkvpa_data.id_kategorie';

    public function handle(): int
    {
        intro('Kontrola shody edi_head.edi_category_id ↔ vkvpa_data.id_kategorie');

        $pairs = DB::table('vkvpa_data as d')
            ->join('edi_head as h', 'h.id', '=', 'd.edihead_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(h.edi_category_id = d.id_kategorie) AS shoda')
            ->selectRaw('SUM(h.edi_category_id IS NOT NULL AND d.id_kategorie IS NOT NULL AND h.edi_category_id <> d.id_kategorie) AS rozdil')
            ->selectRaw('SUM(h.edi_category_id IS NULL AND d.id_kategorie IS NOT NULL) AS head_null')
            ->selectRaw('SUM(h.edi_category_id IS NOT NULL AND d.id_kategorie IS NULL) AS data_null')
            ->first();

        $total = self::int($pairs->total ?? 0);
        $shoda = self::int($pairs->shoda ?? 0);
        $rozdil = self::int($pairs->rozdil ?? 0);
        $headNull = self::int($pairs->head_null ?? 0);
        $dataNull = self::int($pairs->data_null ?? 0);

        table(
            ['Metrika', 'Počet'],
            [
                ['Propojených párů (vkvpa_data ↔ edi_head)', (string) $total],
                ['Shoda kategorie', (string) $shoda],
                ['Rozdíl (obě vyplněné, liší se)', (string) $rozdil],
                ['edi_head NULL, vkvpa_data má kategorii', (string) $headNull],
                ['edi_head má kategorii, vkvpa_data NULL', (string) $dataNull],
            ],
        );

        if ($rozdil > 0) {
            warning('Rozdíly v zařazení (kategorie z hlavičky ≠ kategorie příspěvku):');
            table(
                ['z hlavičky (edi_head)', 'příspěvek (vkvpa_data)', 'počet', 'příklad edi_head.id'],
                $this->mismatchBreakdown(),
            );
        }

        if ($rozdil === 0 && $headNull === 0) {
            outro(sprintf('OK – všech %d propojených párů souhlasí.', $total));

            return self::SUCCESS;
        }

        note('Rozdíly jsou jen reportované, data se nepřepisují. edi_head.edi_category_id '
            .'odráží text hlavičky, vkvpa_data.id_kategorie skutečné zařazení příspěvku.');

        return self::SUCCESS;
    }

    /**
     * Rozpad rozdílů: „band/section/variant z hlavičky" vs totéž z příspěvku,
     * s počtem a ukázkovým id. Seřazeno od nejčastějšího.
     *
     * @return array<int, array{string, string, string, string}>
     */
    private function mismatchBreakdown(): array
    {
        return DB::table('vkvpa_data as d')
            ->join('edi_head as h', 'h.id', '=', 'd.edihead_id')
            ->join('edi_category as ch', 'ch.id', '=', 'h.edi_category_id')
            ->join('edi_category as cd', 'cd.id', '=', 'd.id_kategorie')
            ->whereColumn('h.edi_category_id', '<>', 'd.id_kategorie')
            ->groupBy('ch.name', 'cd.name')
            ->selectRaw('ch.name AS head_name, cd.name AS data_name, COUNT(*) AS n, MIN(h.id) AS sample')
            ->orderByDesc('n')
            ->limit(30)
            ->get()
            ->map(static fn (object $r): array => [
                self::str($r->head_name),
                self::str($r->data_name),
                self::str($r->n),
                self::str($r->sample),
            ])
            ->values()
            ->all();
    }

    /** Bezpečný převod skalární DB hodnoty na string (kvůli PHPStan mixed). */
    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** Bezpečný převod numerické DB hodnoty (SUM/COUNT) na int. */
    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
