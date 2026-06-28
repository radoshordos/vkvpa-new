<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\Edihead;
use Illuminate\Support\Facades\DB;

/**
 * Naplní `edi_head.edi_category_id` 1:1 z kategorie příspěvku
 * (`edi_entries.category_id`), která je autoritativní – EDI hlavička bývá
 * prázdná, oříznutá nebo protiřečí skutečnému zařazení, takže se z ní kategorie
 * neodvozuje.
 *
 * Pravidlo pro každý řádek `edi_head`:
 *   - právě jeden příspěvek (resp. víc příspěvků téže kategorie) → ta kategorie,
 *   - víc příspěvků v různých kategoriích (nejednoznačné) → NULL,
 *   - žádný příspěvek (osiřelý deník) → NULL.
 *
 * Idempotentní.
 */
final class EdiheadCategoryBackfiller
{
    private const int CHUNK = 500;

    /**
     * @return int počet reálně změněných řádků (v dry-run kolik BY se změnilo)
     */
    public function mirrorEdiEntryCategory(bool $dryRun = false): int
    {
        // pro každou hlavičku jednoznačná kategorie příspěvků, jinak null
        $desired = [];
        DB::table('edi_entries')
            ->whereNotNull('edi_head_id')
            ->groupBy('edi_head_id')
            ->selectRaw('edi_head_id AS head_id, MIN(category_id) AS kat, COUNT(DISTINCT category_id) AS cnt')
            ->get()
            ->each(function (object $r) use (&$desired): void {
                $headId = self::toInt($r->head_id);
                $desired[$headId] = self::toInt($r->cnt) === 1 ? self::toInt($r->kat) : null;
            });

        $changed = 0;
        /** @var array<int|string, list<int>> $updates  cílová kategorie (int) nebo 'null' => [head_id, …] */
        $updates = [];

        Edihead::query()
            ->select(['id', 'edi_category_id'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($heads) use ($desired, &$changed, &$updates): void {
                foreach ($heads as $head) {
                    $target = $desired[$head->id] ?? null; // osiřelé i víceznačné → null
                    $cur = $head->edi_category_id;

                    if ($cur === $target) {
                        continue;
                    }

                    $changed++;
                    $updates[$target ?? 'null'][] = (int) $head->id;
                }
            });

        if (! $dryRun) {
            foreach ($updates as $key => $headIds) {
                $value = $key === 'null' ? null : (int) $key;
                foreach (array_chunk($headIds, self::CHUNK) as $batch) {
                    DB::table('edi_head')->whereIn('id', $batch)->update(['edi_category_id' => $value]);
                }
            }
        }

        return $changed;
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
