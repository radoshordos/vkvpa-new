<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Support\ContestWindow;
use App\Support\Maidenhead;

/**
 * Kontrola kvality naparsovaného EDI deníku – hledá nefatální problémy, které
 * snižují skóre nebo značí chybu (duplicitní značky, vadné/prázdné lokátory,
 * QSO mimo závodní okno či den, nesoulad deklarovaného a skutečného počtu).
 *
 * Pracuje čistě nad {@see EdiLog} (bez DB). Pravidla pro okno/den kopírují
 * {@see ScoringService::scoreEdi()}, aby počty odpovídaly
 * tomu, co se reálně (ne)započítá.
 */
final class EdiValidator
{
    public function validate(EdiLog $log): EdiValidationReport
    {
        $den = ContestWindow::dayFromTDate($log->header->tDate());
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        /** @var array<string, int> $callCounts */
        $callCounts = [];
        /** @var list<string> $invalid */
        $invalid = [];
        $empty = 0;
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

            // Stejné pořadí vyloučení jako ve scoreEdi: okno → den → prázdný WWL.
            $time = trim($qso->time);
            $square = Maidenhead::bigSquare($wwl);
            if (! ($time >= $from && $time <= $to)) {
                $outOfWindow++;
            } elseif ($den !== '' && trim($qso->date) !== $den) {
                $wrongDate++;
            } elseif ($square === '') {
                $empty++;
            }
        }

        $duplicates = array_filter($callCounts, static fn (int $n): bool => $n > 1);
        arsort($duplicates);

        $pWWLo = trim($log->header->pWWLo());

        return new EdiValidationReport(
            duplicateCalls: $duplicates,
            invalidLocators: $invalid,
            emptyLocators: $empty,
            outOfWindow: $outOfWindow,
            wrongDate: $wrongDate,
            declaredTotal: $log->declaredTotal,
            parsedCount: $log->qsoCount(),
            lineErrors: $log->lineErrors,
            ignoredLines: count($log->ignoredLines),
            invalidHomeLocator: Maidenhead::isValidLocator($pWWLo) ? null : $pWWLo,
        );
    }
}
