<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\QsoMode;
use App\Enums\Severity;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Models\EdiLine;
use App\Services\Edi\DenikStatistiky;
use App\Support\ContestWindow;
use App\Support\Finding;
use App\Support\Maidenhead;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Administrativní kontrola hlášení před schválením (převzetím).
 *
 * Generuje varování specifická pro vyhodnocovatele – závodník je nevidí.
 * Každé varování je srozumitelný český text připravený k přímému zobrazení.
 */
final class AdminEntryChecker
{
    private const int RATE_WINDOW_MIN = 10;

    private const int RATE_THRESHOLD = 15;

    /**
     * Vrátí seznam varování relevantních pro schvalování záznamu.
     * Prázdný seznam = žádné podezřelé nálezy.
     *
     * @return list<Finding>
     */
    public function warnings(EdiEntry $entry): array
    {
        $findings = [];

        foreach ($this->contactWarnings($entry) as $w) {
            $findings[] = $w;
        }

        if ($entry->edi_head_id === null) {
            return $findings; // ruční hlášení bez EDI – EDI kontroly přeskočit
        }

        $head = EdiHead::with(['lines' => static fn (HasMany $q) => $q->select('edi_head_id', 'call_sign', 'qso_at', 'received_wwl', 'mode_code')])
            ->find($entry->edi_head_id);

        if ($head === null) {
            return $findings;
        }

        foreach ($this->selfQsoWarnings($head) as $w) {
            $findings[] = $w;
        }

        // Jednorázová varování: admin-only (tempo, křížová kontrola) + kategorie-2
        // (lokátor, duplicity, okno – zrcadlí logiku EdiValidationReport::fromLog()).
        foreach (array_filter([
            $this->operatingRateWarning($head->lines),
            $this->crossCheckWarning($head, (int) $entry->round_id),
            $this->invalidHomeLocatorWarning($head),
            $this->duplicateCallsWarning($head->lines),
            $this->invalidLocatorsWarning($head->lines),
            $this->invalidModeCodesWarning($head->lines),
            $this->outOfWindowWarning($head->lines),
        ]) as $w) {
            $findings[] = $w;
        }

        return $findings;
    }

    // ── Chybějící kontaktní údaje ─────────────────────────────────────────────

    /** @return list<Finding> */
    private function contactWarnings(EdiEntry $entry): array
    {
        $findings = [];

        if (trim((string) $entry->name) === '') {
            $findings[] = new Finding(
                Severity::Warning,
                'Chybí jméno operátora – pole Jméno je prázdné.',
            );
        }

        if (trim((string) $entry->email) === '') {
            $findings[] = new Finding(
                Severity::Warning,
                'Chybí kontaktní e-mail – závodníkovi nelze odeslat potvrzení ani ho kontaktovat.',
            );
        }

        return $findings;
    }

    // ── Self-QSO ─────────────────────────────────────────────────────────────

    /** @return list<Finding> */
    private function selfQsoWarnings(EdiHead $head): array
    {
        $myCall = strtoupper(trim((string) $head->p_call));
        $findings = [];

        foreach ($head->lines as $l) {
            if (strtoupper(trim((string) $l->call_sign)) === $myCall) {
                $cas = $l->qso_at?->utc()->format('H:i') ?? '—';
                $findings[] = new Finding(
                    Severity::Warning,
                    'Self-QSO v '.$cas.' – stanice navázala spojení sama se sebou (chyba loggeru, QSO se nezapočítá).',
                );
            }
        }

        return $findings;
    }

    // ── Neobvyklé tempo provozu ───────────────────────────────────────────────

    /**
     * Detekuje podezřele vysoký počet QSO v krátkém okně.
     * Práh: > 15 QSO za 10 minut. Klouzavé okno nad seřazenými časy.
     *
     * @param  Collection<int, EdiLine>  $lines
     */
    private function operatingRateWarning(Collection $lines): ?Finding
    {
        /** @var list<int> $times */
        $times = $lines
            ->map(static fn (EdiLine $l): int => $l->timeMinutes)
            ->filter(static fn (int $t): bool => $t > 0)
            ->sort()
            ->values()
            ->all();

        $n = count($times);
        $max = 0;
        $maxWindow = null;

        for ($i = 0, $j = 0; $i < $n; $i++) {
            while ($times[$i] - $times[$j] > self::RATE_WINDOW_MIN) {
                $j++;
            }
            $cnt = $i - $j + 1;
            if ($cnt > $max) {
                $max = $cnt;
                $maxWindow = DenikStatistiky::hhmm($times[$j]).'–'.DenikStatistiky::hhmm($times[$i]);
            }
        }

        if ($max > self::RATE_THRESHOLD) {
            return new Finding(
                Severity::Warning,
                sprintf(
                    'Neobvyklé tempo provozu: %d QSO za %d minut (%s). Může naznačovat upravený nebo automaticky generovaný log.',
                    $max,
                    self::RATE_WINDOW_MIN,
                    (string) $maxWindow,
                ),
            );
        }

        return null;
    }

    // ── Křížová kontrola ─────────────────────────────────────────────────────

    /**
     * Informuje o počtu protistaní z tohoto deníku, které mají odevzdán
     * vlastní log v tomto kole – admin může porovnat záznamy ručně.
     * (Plná automatická křížová kontrola závisí na dostupnosti všech logů.)
     */
    private function crossCheckWarning(EdiHead $head, int $koloId): ?Finding
    {
        if ($koloId === 0) {
            return null;
        }

        $workedCalls = $head->lines
            ->pluck('call_sign')
            ->map(static fn (mixed $c): string => strtoupper(trim(is_string($c) ? $c : '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($workedCalls === []) {
            return null;
        }

        $count = EdiHead::query()
            ->whereIn('p_call', $workedCalls)
            ->where('round_id', $koloId)
            ->where('p_call', '!=', strtoupper(trim((string) $head->p_call)))
            ->count();

        if ($count === 0) {
            return null;
        }

        return new Finding(
            Severity::Info,
            sprintf(
                'Křížová kontrola: %d z pracovaných protistaní má v tomto kole odevzdán log – doporučeno porovnat záznamy.',
                $count,
            ),
        );
    }

    // ── Neplatný domácí lokátor ───────────────────────────────────────────────

    private function invalidHomeLocatorWarning(EdiHead $head): ?Finding
    {
        $pWWLo = trim((string) $head->p_wwlo);

        if ($pWWLo === '') {
            return new Finding(
                Severity::Warning,
                'Domácí lokátor (PWWLo) je prázdný – vzdálenosti ani body nelze spočítat, skóre bude 0.',
            );
        }

        if (! Maidenhead::isValidLocator($pWWLo)) {
            return new Finding(
                Severity::Warning,
                'Domácí lokátor „'.$pWWLo.'" (PWWLo) není platný Maidenhead formát – skóre bude 0.',
            );
        }

        return null;
    }

    // ── Duplicitní spojení ────────────────────────────────────────────────────

    /** @param Collection<int, EdiLine> $lines */
    private function duplicateCallsWarning(Collection $lines): ?Finding
    {
        // array_count_values is typed array<string,int> by PHPStan stubs → safe at level 10.
        $calls = $lines
            ->map(static fn (EdiLine $l): string => strtoupper(trim((string) $l->call_sign)))
            ->filter(static fn (string $c): bool => $c !== '')
            ->values()
            ->all();

        $dupes = array_filter(
            array_count_values($calls),
            static fn (int $n): bool => $n > 1,
        );

        if ($dupes === []) {
            return null;
        }

        arsort($dupes);
        $total = count($dupes);
        $parts = [];
        foreach (array_slice($dupes, 0, 8, true) as $call => $count) {
            $parts[] = $call.' ('.$count.'×)';
        }
        $more = $total > 8 ? ' …' : '';

        return new Finding(
            Severity::Warning,
            'Duplicitní spojení (stanice navázána víckrát): '.implode(', ', $parts).$more.'. Duplicity se bodují 0.',
        );
    }

    // ── Neplatné lokátory protistanice ───────────────────────────────────────

    /** @param Collection<int, EdiLine> $lines */
    private function invalidLocatorsWarning(Collection $lines): ?Finding
    {
        $invalid = $lines
            ->filter(static function (EdiLine $l): bool {
                $wwl = trim((string) $l->received_wwl);

                return $wwl !== '' && ! Maidenhead::isValidLocator($wwl);
            })
            ->take(8)
            ->map(static function (EdiLine $l): string {
                $call = strtoupper(trim((string) $l->call_sign));
                $wwl = trim((string) $l->received_wwl);

                return ($call !== '' ? $call : '?').': '.$wwl;
            })
            ->values();

        if ($invalid->isEmpty()) {
            return null;
        }

        return new Finding(
            Severity::Warning,
            'Neplatný WWL lokátor u spojení: '.$invalid->implode(', ').'.',
        );
    }

    // ── Chybný kód druhu provozu ─────────────────────────────────────────────

    /**
     * Upozorní na QSO s kódem provozu mimo oficiálně povolené 1–6 (SSB, CW,
     * SSB/CW, CW/SSB, AM, FM). Takový kód (0/prázdný, dřívější MGM/SSTV/ATV,
     * nebo rozhozený sloupec s RST) se mapuje na {@see QsoMode::Other} a počítá
     * se jako „Ostatní". Jen informativní – body to neovlivní.
     *
     * @param  Collection<int, EdiLine>  $lines
     */
    private function invalidModeCodesWarning(Collection $lines): ?Finding
    {
        $invalid = $lines->filter(static fn (EdiLine $l): bool => $l->mode === QsoMode::Other);
        $total = $invalid->count();

        if ($total === 0) {
            return null;
        }

        $sample = $invalid
            ->take(8)
            ->map(static function (EdiLine $l): string {
                $call = strtoupper(trim((string) $l->call_sign));

                return ($call !== '' ? $call : '?').': '.$l->modeCode;
            })
            ->values();

        $more = $total > 8 ? ' … (+'.($total - 8).')' : '';

        return new Finding(
            Severity::Info,
            'Chybný kód druhu provozu (mimo povolené 1–6) u '.$total.' QSO: '
                .$sample->implode(', ').$more.'. Tato spojení se počítají jako „Ostatní".',
        );
    }

    // ── QSO mimo závodní okno ────────────────────────────────────────────────

    /** @param Collection<int, EdiLine> $lines */
    private function outOfWindowWarning(Collection $lines): ?Finding
    {
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        $count = $lines->filter(static function (EdiLine $l) use ($from, $to): bool {
            $hhmm = $l->qso_at?->utc()->format('Hi');

            return $hhmm !== null && ! ($hhmm >= $from && $hhmm <= $to);
        })->count();

        if ($count === 0) {
            return null;
        }

        return new Finding(
            Severity::Info,
            $count.' QSO mimo závodní okno ('.$from.'–'.$to.' UTC) – tato spojení se nezapočítávají.',
        );
    }
}
