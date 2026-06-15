<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\EdiParseException;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiQso;
use App\Services\Edi\EdiReducer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Doplní sloupec edilines.qso_at (a u legacy deníků i date/time/mode_code)
 * zpětně z uloženého surového EDI (edihead.src).
 *
 * Důvod: starší deníky (snapshoty kol 130/131) mají v edilines prázdné
 * date/time, takže je vizualizace ani skórování nezachytí v závodním okně
 * (filtr přes 'time'). Surové EDI je přitom uloženo ve sloupci src, odkud lze
 * původní hodnoty bezpečně obnovit – nejde o odhad, ale o re-parse přesně toho,
 * co účastník nahrál (ověřeno: přepočet skóre sedí na uložené výsledky).
 *
 * Strategie per deník:
 *  - „full": src se naparsuje, počet QSO i pořadí lokátorů sedí na edilines →
 *    doplní se date, time, mode_code i qso_at podle pořadí.
 *  - „fallback": src chybí/nejde naparsovat/nesedí počet, ale řádky už mají
 *    date+time → dopočítá se aspoň qso_at z nich; date/time/mode zůstávají.
 *  - „skip": ani jedno nejde → deník se vynechá a vypíše.
 *
 * Idempotentní: zpracuje jen s deníky, kde aspoň jeden řádek nemá qso_at.
 * Spuštění: `php artisan edilines:backfill-qso-at [--dry-run] [--head=ID]`.
 */
final class BackfillEdilineQsoAt extends Command
{
    protected $signature = 'edilines:backfill-qso-at {--dry-run : Jen vypsat, co by se stalo, bez zápisu} {--head= : Omezit na jeden edihead.id} {--simple : Jen složit qso_at z existujícího date+time; legacy deníky vyžadující re-parse src přeskočit} {--lenient : Když striktní parser src odmítne (FM/mód 6 s prázdným Received-RST, odfiltrovaná „error“ QSO), zkusit tolerantní rozsplit QSO řádků dle „;“}';

    protected $description = 'Doplní edilines.qso_at (a u legacy deníků date/time/mode) ze surového EDI (edihead.src).';

    public function handle(EdiParser $parser, EdiReducer $reducer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $simple = (bool) $this->option('simple');
        $lenient = (bool) $this->option('lenient');

        $query = Edihead::query()
            ->whereHas('lines', fn ($q) => $q->whereNull('qso_at'))
            ->orderBy('id');

        if ($this->option('head') !== null) {
            $query->where('id', (int) $this->option('head'));
        }

        $heads = 0;
        $full = 0;
        $lenientCount = 0;
        $fallback = 0;
        $skipped = 0;
        $rowsUpdated = 0;

        // Chunkování po id: src deníků je velký text, načítat všech 369 najednou
        // by vyčerpalo paměť. Každý chunk se po zpracování uvolní.
        $query->chunkById(50, function (Collection $chunk) use ($parser, $reducer, $dryRun, $simple, $lenient, &$heads, &$full, &$lenientCount, &$fallback, &$skipped, &$rowsUpdated): void {
            foreach ($chunk as $head) {
                $heads++;
                $result = $this->backfillHead($parser, $reducer, $head, $dryRun, $simple, $lenient);
                $rowsUpdated += $result['rows'];

                match ($result['mode']) {
                    'full' => $full++,
                    'lenient' => $lenientCount++,
                    'fallback' => $fallback++,
                    default => $skipped++,
                };

                if ($result['mode'] === 'skip') {
                    $this->warn(sprintf('skip   #%d %s – %s', $head->id, $head->p_call, $result['note']));
                }
            }
        });

        $this->info(sprintf(
            '%sdeníků: %d (full %d, lenient %d, fallback %d, skip %d), upraveno řádků: %d',
            $dryRun ? '[dry-run] ' : '',
            $heads,
            $full,
            $lenientCount,
            $fallback,
            $skipped,
            $rowsUpdated,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{mode: 'full'|'lenient'|'fallback'|'skip', rows: int, note: string}
     */
    private function backfillHead(EdiParser $parser, EdiReducer $reducer, Edihead $head, bool $dryRun, bool $simple = false, bool $lenient = false): array
    {
        $rows = $head->lines()->orderBy('id')->get(['id', 'date', 'time', 'received_wwl']);

        // 1) Modern deník: řádky už mají date+time → qso_at se složí z nich,
        //    src netřeba a uložená date/time/mode se nepřepisují. Jakmile jeden
        //    řádek date/time postrádá, jde o legacy deník → krok 2.
        $fallback = [];
        $allHaveDateTime = $rows->isNotEmpty();

        foreach ($rows as $row) {
            $at = EdiQso::combineDateTime((string) $row->date, (string) $row->time);
            if ($at !== null) {
                $fallback[(int) $row->id] = ['qso_at' => $at];
            } else {
                $allHaveDateTime = false;
            }
        }

        if ($allHaveDateTime) {
            $this->applyUpdates($fallback, $dryRun);

            return ['mode' => 'fallback', 'rows' => count($fallback), 'note' => ''];
        }

        // V --simple režimu se src nepřepisuje: doplň aspoň qso_at u řádků,
        // které date+time mají, a legacy deník nech na pozdější plný běh.
        if ($simple) {
            if ($fallback !== []) {
                $this->applyUpdates($fallback, $dryRun);

                return ['mode' => 'fallback', 'rows' => count($fallback), 'note' => ''];
            }

            return ['mode' => 'skip', 'rows' => 0, 'note' => 'legacy (prázdné date/time) – vyžaduje re-parse src, přeskočeno v --simple'];
        }

        // 2) Legacy deník (prázdné date/time): obnov date/time/mode/qso_at ze
        //    src podle pořadí. edilines drží jen QSO v okně (po EDIR), proto
        //    zkusíme jak plný src, tak jeho ořez reduce(src) – použijeme ten,
        //    který sedne počtem QSO i pořadím lokátorů.
        $raw = (string) $head->src;

        // Striktní kandidáti (plný src + EDIR ořez). Lenientní rozsplit se zkusí
        // až nakonec a jen s --lenient, aby striktní výsledek měl přednost.
        $candidates = [
            'full' => $this->parseSrc($parser, $raw),
            'full2' => $this->parseSrc($parser, $reducer->reduce($raw)),
        ];
        if ($lenient) {
            // I lenientní variantu zkus jak na plném src, tak na EDIR ořezu –
            // edilines drží jen QSO v okně, takže u logů s QSO mimo okno sedne
            // až zredukovaný src.
            $candidates['lenient'] = $this->parseSrcLenient($raw);
            $candidates['lenient2'] = $this->parseSrcLenient($reducer->reduce($raw));
        }

        foreach ($candidates as $kind => $qsos) {
            if ($qsos === null || count($qsos) !== $rows->count() || ! $this->wwlOrderMatches($rows, $qsos)) {
                continue;
            }

            $updates = [];

            foreach ($rows as $i => $row) {
                $q = $qsos[$i];
                $updates[(int) $row->id] = [
                    'date' => $q->date,
                    'time' => $q->time,
                    'mode_code' => $q->modeCode === '' ? null : (int) $q->modeCode,
                    'qso_at' => $q->qsoAt(),
                ];
            }

            $this->applyUpdates($updates, $dryRun);

            return ['mode' => str_starts_with($kind, 'lenient') ? 'lenient' : 'full', 'rows' => count($updates), 'note' => ''];
        }

        // 3) Co šlo složit z částečných date/time, aspoň ulož; zbytek nejde.
        if ($fallback !== []) {
            $this->applyUpdates($fallback, $dryRun);

            return ['mode' => 'fallback', 'rows' => count($fallback), 'note' => ''];
        }

        $srcCount = $candidates['full'] === null ? -1 : count($candidates['full']);
        $note = $srcCount === -1
            ? 'src chybí/nejde naparsovat a řádky nemají date+time'
            : sprintf('src dává %d QSO, edilines má %d (nesedí ani po EDIR ořezu)', $srcCount, $rows->count());

        return ['mode' => 'skip', 'rows' => 0, 'note' => $note];
    }

    /**
     * @return list<EdiQso>|null null = src prázdné nebo neparsovatelné
     */
    private function parseSrc(EdiParser $parser, string $src): ?array
    {
        if (trim($src) === '') {
            return null;
        }

        try {
            return $parser->parse($src)->qsos;
        } catch (EdiParseException) {
            return null;
        }
    }

    /**
     * Tolerantní extrakce QSO řádků ze src jen pro doplnění času: čte sekci
     * [QSORecords;N] řádek po řádku a každý rozsplitne podle „;“ na pevné pozice
     * (date, time, …, received_wwl). Na rozdíl od striktního parseru NEvyžaduje
     * Received-RST/číslo (FM/mód 6 logy je mají prázdné) a NEodfiltrovává řádky
     * s „error“ značkou (zůstaly i v edilines) – proto sedí počtem i pořadím.
     * Bezpečnost zajišťuje až následná kontrola počtu QSO + pořadí lokátorů.
     *
     * @return list<EdiQso>|null null = ve src není sekce QSO
     */
    private function parseSrcLenient(string $src): ?array
    {
        if (trim($src) === '') {
            return null;
        }

        $inRecords = false;
        $qsos = [];

        foreach (preg_split('/\r\n|\r|\n/', $src) ?: [] as $line) {
            $line = trim($line);

            if (str_starts_with($line, '[QSORecords;')) {
                $inRecords = true;

                continue;
            }
            if (! $inRecords) {
                continue;
            }
            if (str_starts_with($line, '[')) {
                // Konec sekce ([END…], [Remarks] apod.).
                break;
            }
            if ($line === '') {
                continue;
            }

            $f = explode(';', $line);
            if (count($f) < 10) {
                continue; // není to QSO řádek
            }

            $qsos[] = new EdiQso(
                date: $f[0], time: $f[1], callSign: $f[2], modeCode: $f[3],
                sentRst: $f[4], sentQsoNumber: $f[5], receivedRst: $f[6],
                receivedQsoNumber: $f[7], receivedExchange: $f[8], receivedWwl: $f[9],
                qsoPoints: $f[10] ?? '', newExchange: $f[11] ?? '', newWwl: $f[12] ?? '',
                newDxcc: $f[13] ?? '', duplicate: $f[14] ?? '',
            );
        }

        return $qsos === [] ? null : $qsos;
    }

    /**
     * Pořadí naparsovaných QSO musí sednout na uložené řádky podle lokátoru –
     * pojistka, že párování po pozici míří na správný řádek.
     *
     * @param  Collection<int, Ediline>  $rows
     * @param  list<EdiQso>  $qsos
     */
    private function wwlOrderMatches(Collection $rows, array $qsos): bool
    {
        foreach ($rows as $i => $row) {
            if (strtoupper(trim((string) $row->received_wwl)) !== strtoupper(trim($qsos[$i]->receivedWwl))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $updates  id řádku → změněné sloupce
     */
    private function applyUpdates(array $updates, bool $dryRun): void
    {
        if ($dryRun || $updates === []) {
            return;
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $id => $values) {
                DB::table('edilines')->where('id', $id)->update($values);
            }
        });
    }
}
