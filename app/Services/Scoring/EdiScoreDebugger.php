<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Services\Edi\EdiLog;
use App\Support\ContestWindow;

/**
 * Debug analyzátor bodování EDI deníku.
 *
 * Replikuje stejný výpočet jako {@see ScoringService::scoreEdi()}, ale místo
 * jediného skóre vrací rozpad řádek po řádku – u každého QSO je vidět, zda se
 * započítává a proč (ne). Pracuje nad naparsovaným {@see EdiLog} (bez DB),
 * takže slouží čistě k náhledu / kontrole; nic neukládá.
 *
 * Pravidla (shodná se scoreEdi):
 *   - domácí velký čtverec = první 4 znaky PWWLo,
 *   - započítá se QSO v závodním okně (čas 08:00–11:00 UTC) a ve dni závodu
 *     (YYMMDD z TDate), jehož přijatý velký čtverec je cizí a neprázdný,
 *   - pocet = počet takových QSO, nasobice = počet různých cizích čtverců + 1,
 *   - body = pocet × nasobice.
 */
final class EdiScoreDebugger
{
    public function analyze(EdiLog $log): EdiDebugReport
    {
        $header = $log->header;
        $home = strtoupper(substr(trim($header->pWWLo()), 0, 4));
        // Den závodu = YYMMDD ze začátku TDate (formát YYYYMMDD;YYYYMMDD).
        $den = substr(trim($header->tDate()), 2, 6);
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        /** @var list<EdiDebugRow> $rows */
        $rows = [];
        /** @var list<string> $foreignSquares cizí čtverce v pořadí prvního výskytu */
        $foreignSquares = [];

        $pocet = 0;
        $outOfWindow = 0;
        $wrongDate = 0;
        $ownSquare = 0;
        $emptyWwl = 0;
        $duplicates = 0;

        $index = 0;
        foreach ($log->qsos as $qso) {
            $index++;

            $time = trim($qso->time);
            $date = trim($qso->date);
            $square = strtoupper(substr(trim($qso->receivedWwl), 0, 4));

            $inWindow = $time >= $from && $time <= $to;
            $dateMatches = $den === '' || $date === $den;
            $isEmpty = $square === '';
            $isOwn = $square === $home;
            $duplicate = strtoupper(trim($qso->duplicate)) === 'D';
            if ($duplicate) {
                $duplicates++;
            }

            $counted = false;
            $newMultiplier = false;

            // Pořadí důvodů kopíruje filtr scoreEdi: nejdřív čas, pak den, pak čtverec.
            if (! $inWindow) {
                $reason = 'out_of_window';
                $outOfWindow++;
            } elseif (! $dateMatches) {
                $reason = 'wrong_date';
                $wrongDate++;
            } elseif ($isEmpty) {
                $reason = 'empty_wwl';
                $emptyWwl++;
            } elseif ($isOwn) {
                $reason = 'own_square';
                $ownSquare++;
            } else {
                $reason = 'counted';
                $counted = true;
                $pocet++;
                if (! in_array($square, $foreignSquares, true)) {
                    $foreignSquares[] = $square;
                    $newMultiplier = true;
                }
            }

            $rows[] = new EdiDebugRow(
                index: $index,
                date: $date,
                time: $time,
                callSign: $qso->callSign,
                receivedWwl: $qso->receivedWwl,
                bigSquare: $square,
                inWindow: $inWindow,
                dateMatches: $dateMatches,
                isOwnSquare: $isOwn,
                isEmptySquare: $isEmpty,
                counted: $counted,
                newMultiplier: $newMultiplier,
                duplicate: $duplicate,
                reason: $reason,
            );
        }

        $nasobice = count($foreignSquares) + 1;

        return new EdiDebugReport(
            call: $header->pCall(),
            locator: $header->pWWLo(),
            homeSquare: $home,
            contestDay: $den,
            tDate: $header->tDate(),
            band: $header->pBand(),
            section: $header->pSect(),
            power: $header->sPowe(),
            qrp: $header->isQrp(),
            windowFrom: $from,
            windowTo: $to,
            declaredTotal: $log->declaredTotal,
            parsedCount: $log->qsoCount(),
            rows: $rows,
            ignoredLines: $log->ignoredLines,
            lineErrors: $log->lineErrors,
            pocet: $pocet,
            nasobice: $nasobice,
            body: $pocet * $nasobice,
            excludedOutOfWindow: $outOfWindow,
            excludedWrongDate: $wrongDate,
            excludedOwnSquare: $ownSquare,
            excludedEmpty: $emptyWwl,
            duplicateCount: $duplicates,
        );
    }
}
