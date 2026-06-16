<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\Severity;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaData;
use App\Services\Edi\DenikStatistiky;
use App\Support\Finding;
use Illuminate\Support\Collection;

/**
 * Administrativní kontrola hlášení před schválením (převzetím).
 *
 * Generuje varování specifická pro vyhodnocovatele – závodník je nevidí.
 * Každé varování je srozumitelný český text připravený k přímému zobrazení.
 */
final class AdminEntryChecker
{
    /**
     * Vrátí seznam varování relevantních pro schvalování záznamu.
     * Prázdný seznam = žádné podezřelé nálezy.
     *
     * @return list<Finding>
     */
    public function warnings(VkvpaData $entry): array
    {
        $findings = [];

        foreach ($this->contactWarnings($entry) as $w) {
            $findings[] = $w;
        }

        if ($entry->edihead_id === null) {
            return $findings; // ruční hlášení bez EDI – EDI kontroly přeskočit
        }

        $head = Edihead::with(['lines' => static fn ($q) => $q->select('edihead_id', 'call_sign', 'time')])
            ->find($entry->edihead_id);

        if ($head === null) {
            return $findings;
        }

        if (trim((string) $head->p_call) === '') {
            $findings[] = new Finding(
                Severity::Fatal,
                'Deník nemá volací značku (PCall) – hlášení nelze přiřadit závodníkovi. Záznam by měl být smazán.',
            );
        }

        foreach ($this->selfQsoWarnings($head) as $w) {
            $findings[] = $w;
        }

        $rateWarning = $this->operatingRateWarning($head->lines);
        if ($rateWarning !== null) {
            $findings[] = $rateWarning;
        }

        $crossWarning = $this->crossCheckWarning($head, (int) $entry->id_kola);
        if ($crossWarning !== null) {
            $findings[] = $crossWarning;
        }

        return $findings;
    }

    // ── Chybějící kontaktní údaje ─────────────────────────────────────────────

    /** @return list<Finding> */
    private function contactWarnings(VkvpaData $entry): array
    {
        $findings = [];

        if (trim((string) $entry->jmeno) === '') {
            $findings[] = new Finding(
                Severity::Warning,
                'Chybí jméno operátora – pole Jméno je prázdné.',
            );
        }

        if (trim((string) $entry->mail) === '') {
            $findings[] = new Finding(
                Severity::Warning,
                'Chybí kontaktní e-mail – závodníkovi nelze odeslat potvrzení ani ho kontaktovat.',
            );
        }

        return $findings;
    }

    // ── Self-QSO ─────────────────────────────────────────────────────────────

    /**
     * Spojení, kde volačka protistanice = vlastní volačka (chyba loggeru).
     *
     * @param  Collection<int, Ediline>  $lines
     * @return list<Finding>
     */
    private function selfQsoWarnings(Edihead $head): array
    {
        $myCall = strtoupper(trim((string) $head->p_call));
        $findings = [];

        foreach ($head->lines as $l) {
            if (strtoupper(trim((string) $l->call_sign)) === $myCall) {
                $t = trim((string) $l->time);
                $cas = strlen($t) === 4 ? substr($t, 0, 2).':'.substr($t, 2, 2) : $t;
                $findings[] = new Finding(
                    Severity::Warning,
                    'Self-QSO v '.$cas.' – stanice navázala spojení sama se sebou (chyba loggeru, QSO se nezapočítá).',
                );
            }
        }

        return $findings;
    }

    // ── Neobvyklé tempo provozu ───────────────────────────────────────────────

    private const int RATE_WINDOW_MIN = 10;
    private const int RATE_THRESHOLD = 15;

    /**
     * Detekuje podezřele vysoký počet QSO v krátkém okně.
     * Práh: > 15 QSO za 10 minut. Klouzavé okno nad seřazenými časy.
     *
     * @param  Collection<int, Ediline>  $lines
     */
    private function operatingRateWarning(Collection $lines): ?Finding
    {
        /** @var list<int> $times */
        $times = $lines
            ->map(static fn (Ediline $l): int => DenikStatistiky::minutes(trim((string) $l->time)))
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
     *
     * @param  Collection<int, Ediline>  $lines
     */
    private function crossCheckWarning(Edihead $head, int $koloId): ?Finding
    {
        if ($koloId === 0) {
            return null;
        }

        $workedCalls = $head->lines
            ->pluck('call_sign')
            ->map(static fn (mixed $c): string => strtoupper(trim((string) $c)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($workedCalls === []) {
            return null;
        }

        $count = Edihead::query()
            ->whereIn('p_call', $workedCalls)
            ->where('id_kola', $koloId)
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
}
