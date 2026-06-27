<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Actions\ImportEdiAction;
use App\Enums\QsoCountStatus;
use App\Enums\RstPropagation;
use App\Support\ContestWindow;
use App\Support\Maidenhead;

/**
 * Souhrn nálezů kontroly kvality EDI deníku po importu (nefatální upozornění).
 *
 * Na rozdíl od výjimek v {@see ImportEdiAction} tyto nálezy import
 * nezastaví – jen upozorní závodníka na věci, které snižují bodový zisk nebo
 * mohou značit chybu v deníku (duplicity, vadné lokátory, QSO mimo závod).
 */
final readonly class EdiValidationReport
{
    /**
     * @param  array<string, int>  $duplicateCalls  značka → počet výskytů (jen >1)
     * @param  list<string>  $invalidLocators  ukázka „ZNAČKA: WWL" s vadným lokátorem
     * @param  list<string>  $lineErrors  QSO odmítnutá parserem (neplatný Maidenhead)
     * @param  list<string>  $invalidReports  ukázky reportů s neplatným tónovým znakem (formát ZNAČKA: REPORT)
     * @param  ?string  $invalidHomeLocator  neplatný PWWLo (null = v pořádku)
     */
    public function __construct(
        public array $duplicateCalls,
        public array $invalidLocators,
        public int $emptyLocators,
        public int $incompleteExchange,
        public int $outOfWindow,
        public int $wrongDate,
        public int $declaredTotal,
        public int $parsedCount,
        public array $lineErrors,
        public int $ignoredLines,
        public array $invalidReports = [],
        public ?string $invalidHomeLocator = null,
    ) {}

    /**
     * @param  ?string  $contestDay  den závodu „YYMMDD" (z data konání kola); když
     *                               null, odvodí se z prvního dne TDate (fallback).
     */
    public static function fromLog(EdiLog $log, ?string $contestDay = null): self
    {
        $den = $contestDay ?? ContestWindow::dayFromTDate($log->header->tDate());
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        /** @var array<string, int> $callCounts */
        $callCounts = [];
        /** @var list<string> $invalid */
        $invalid = [];
        /** @var list<string> $invalidReports */
        $invalidReports = [];
        $empty = 0;
        $incomplete = 0;
        $outOfWindow = 0;
        $wrongDate = 0;

        foreach ($log->qsos as $qso) {
            $call = strtoupper(trim($qso->callSign));
            if ($call !== '') {
                $callCounts[$call] = ($callCounts[$call] ?? 0) + 1;
            }

            $wwl = trim($qso->receivedWwl);
            if ($wwl !== '' && ! Maidenhead::isValidLocator($wwl) && count($invalid) < 8) {
                $invalid[] = ($call !== '' ? $call : '?').': '.$wwl;
            }

            // Třetí znak reportu smí být jen číslice nebo tónové písmeno A/S/M;
            // jiný znak naimportované spojení nezneplatní, jen na něj upozorníme.
            foreach ([$qso->sentRst, $qso->receivedRst] as $report) {
                if (trim($report) !== '' && ! RstPropagation::isValidReport($report) && count($invalidReports) < 8) {
                    $invalidReports[] = ($call !== '' ? $call : '?').': '.strtoupper(trim($report));
                }
            }

            // Stejné pořadí vyloučení jako ve scoreEdi: neúplný příjem → okno →
            // den → prázdný WWL.
            $square = Maidenhead::bigSquare($wwl);
            match (QsoCountStatus::classify($qso->receivedRst, $qso->receivedQsoNumber, $qso->time, $qso->date, $square, $den, $from, $to)) {
                QsoCountStatus::IncompleteExchange => $incomplete++,
                QsoCountStatus::OutOfWindow => $outOfWindow++,
                QsoCountStatus::WrongDate => $wrongDate++,
                QsoCountStatus::EmptyWwl => $empty++,
                QsoCountStatus::Counted => null,
            };
        }

        $duplicates = array_filter($callCounts, static fn (int $n): bool => $n > 1);
        arsort($duplicates);

        $pWWLo = trim($log->header->pWWLo());

        return new self(
            duplicateCalls: $duplicates,
            invalidLocators: $invalid,
            emptyLocators: $empty,
            incompleteExchange: $incomplete,
            outOfWindow: $outOfWindow,
            wrongDate: $wrongDate,
            declaredTotal: $log->declaredTotal,
            parsedCount: $log->qsoCount(),
            lineErrors: $log->lineErrors,
            ignoredLines: count($log->ignoredLines),
            invalidReports: $invalidReports,
            invalidHomeLocator: Maidenhead::isValidLocator($pWWLo) ? null : $pWWLo,
        );
    }

    public function hasWarnings(): bool
    {
        return $this->messages() !== [];
    }

    /**
     * Lidsky čitelná upozornění (cs) pro zobrazení závodníkovi.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        $m = [];

        // ── Problémy v hlavičce (nejzávažnější – vliv na celý deník) ─────────

        if ($this->invalidHomeLocator !== null) {
            $loc = $this->invalidHomeLocator === '' ? '(prázdné)' : '„'.$this->invalidHomeLocator.'"';
            $m[] = 'Domácí lokátor '.$loc.' v poli PWWLo není platný Maidenhead formát – výsledné skóre bude 0, protože vzdálenosti ani body nelze spočítat.';
        }

        // ── QSO odmítnutá parserem (neplatný lokátor) ────────────────────────

        foreach ($this->lineErrors as $err) {
            $m[] = $err;
        }

        // ── Ostatní QSO-úroveň ───────────────────────────────────────────────

        if ($this->duplicateCalls !== []) {
            $list = [];
            foreach (array_slice($this->duplicateCalls, 0, 8, true) as $call => $n) {
                $list[] = $call.' ('.$n.'×)';
            }
            $more = count($this->duplicateCalls) > 8 ? ' …' : '';
            $m[] = 'Duplicitní spojení (stanice navázána víckrát): '.implode(', ', $list).$more.'. Duplicity se bodují 0.';
        }

        if ($this->invalidLocators !== []) {
            $more = count($this->invalidLocators) >= 8 ? ' …' : '';
            $m[] = 'Neplatný WWL lokátor u spojení: '.implode(', ', $this->invalidLocators).$more.'. Zkontroluj deník – chybný lokátor zkresluje body i násobiče.';
        }

        if ($this->invalidReports !== []) {
            $more = count($this->invalidReports) >= 8 ? ' …' : '';
            $m[] = 'Neplatný znak v reportu u spojení: '.implode(', ', $this->invalidReports).$more
                .'. Třetí znak reportu smí být jen číslice nebo písmeno '
                .implode('/', str_split(RstPropagation::letters()))
                .' (aurora/scatter/multipath). Zkontroluj deník – spojení se naimportovalo beze změny.';
        }

        if ($this->incompleteExchange > 0) {
            $m[] = $this->incompleteExchange.' spojení bez přijatého reportu nebo pořadového čísla – podle pravidel jde o neplatná spojení (závodník nepřijal celý soutěžní kód), proto se nezapočítávají.';
        }

        if ($this->emptyLocators > 0) {
            $m[] = $this->emptyLocators.' spojení bez WWL lokátoru – tato spojení se nezapočítávají.';
        }

        if ($this->outOfWindow > 0) {
            $m[] = $this->outOfWindow.' spojení mimo závodní okno – nezapočítávají se.';
        }

        if ($this->wrongDate > 0) {
            $m[] = $this->wrongDate.' spojení mimo den závodu – nezapočítávají se.';
        }

        if ($this->declaredTotal > 0 && $this->declaredTotal !== $this->parsedCount) {
            $m[] = 'Hlavička deklaruje '.$this->declaredTotal.' QSO, ale v deníku jich je '.$this->parsedCount.'.';
        }

        if ($this->ignoredLines > 0) {
            $m[] = $this->ignoredLines.' řádků označených chybou (ERROR) v logu bylo přeskočeno.';
        }

        return $m;
    }
}
