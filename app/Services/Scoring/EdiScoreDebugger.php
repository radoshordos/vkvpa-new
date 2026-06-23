<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Enums\QsoCountStatus;
use App\Exceptions\UnknownBandException;
use App\Models\VkvpaKategorie;
use App\Services\Edi\CategoryResolver;
use App\Services\Edi\EdiLog;
use App\Support\ContestWindow;
use App\Support\Maidenhead;

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
 *     (YYMMDD z TDate), jehož přijatý velký čtverec je neprázdný (vč. vlastního),
 *   - pocet = počet takových QSO, boduZaQso = součet bodů za spojení přepočtených
 *     z lokátorů (QSO-Points z deníku se ignoruje),
 *     nasobice = počet různých cizích čtverců + 1 (vlastní čtverec),
 *   - body = boduZaQso × nasobice.
 */
final class EdiScoreDebugger
{
    public function __construct(private readonly CategoryResolver $categoryResolver) {}

    public function analyze(EdiLog $log): EdiDebugReport
    {
        $header = $log->header;
        $home = Maidenhead::bigSquare($header->pWWLo());
        // Den závodu = YYMMDD ze začátku TDate (formát YYYYMMDD;YYYYMMDD).
        $den = ContestWindow::dayFromTDate($header->tDate());
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        /** @var list<EdiDebugRow> $rows */
        $rows = [];
        /** @var list<string> $foreignSquares cizí čtverce v pořadí prvního výskytu */
        $foreignSquares = [];

        $pocet = 0;
        $boduZaQso = 0;
        $incomplete = 0;
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
            $square = Maidenhead::bigSquare($qso->receivedWwl);
            // Body za spojení přepočítáme z lokátorů, ne z deníku (qsoPoints).
            $points = Maidenhead::qsoPoints($home, $square);

            // Nezávislé příznaky pro rozpad po řádcích (každý se zobrazuje zvlášť).
            $inWindow = $time >= $from && $time <= $to;
            $dateMatches = $den === '' || $date === $den;
            $isEmpty = $square === '';
            $isOwn = $square === $home;
            $duplicate = strtoupper(trim($qso->duplicate)) === 'D';
            if ($duplicate) {
                $duplicates++;
            }

            // Vítězný důvod (a tím i čítače) určuje kanonické pořadí ze sdíleného
            // enumu – shodně se scoreEdi/scoreLog: neúplný příjem, čas, den, WWL.
            $status = QsoCountStatus::classify($qso->receivedRst, $qso->receivedQsoNumber, $time, $date, $square, $den, $from, $to);
            $counted = $status->isCounted();
            $newMultiplier = false;

            if ($counted) {
                $pocet++;
                $boduZaQso += $points;
                // Vlastní velký čtverec se započítává (2 body), není to ale nový
                // cizí násobič – vlastní čtverec je vždy násobičem (ta „+1“).
                if ($isOwn) {
                    $ownSquare++;
                } elseif (! in_array($square, $foreignSquares, true)) {
                    $foreignSquares[] = $square;
                    $newMultiplier = true;
                }
            } else {
                match ($status) {
                    QsoCountStatus::IncompleteExchange => $incomplete++,
                    QsoCountStatus::OutOfWindow => $outOfWindow++,
                    QsoCountStatus::WrongDate => $wrongDate++,
                    QsoCountStatus::EmptyWwl => $emptyWwl++,
                    QsoCountStatus::Counted => null,
                };
            }

            $rows[] = new EdiDebugRow(
                index: $index,
                date: $date,
                time: $time,
                callSign: $qso->callSign,
                receivedWwl: $qso->receivedWwl,
                bigSquare: $square,
                points: $points,
                inWindow: $inWindow,
                dateMatches: $dateMatches,
                isOwnSquare: $isOwn,
                isEmptySquare: $isEmpty,
                counted: $counted,
                newMultiplier: $newMultiplier,
                duplicate: $duplicate,
                reason: $status->value,
            );
        }

        $nasobice = count($foreignSquares) + 1;

        try {
            $categoryId = $this->categoryResolver->resolve(
                $header->pCall(),
                $header->pBand(),
                $header->pSect(),
            );
            $found = $categoryId !== null ? VkvpaKategorie::find($categoryId) : null;
            $categoryName = $found !== null ? (string) $found->nazev : null;
        } catch (UnknownBandException) {
            $categoryId = null;
            $categoryName = null;
        }

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
            boduZaQso: $boduZaQso,
            nasobice: $nasobice,
            body: $boduZaQso * $nasobice,
            excludedIncomplete: $incomplete,
            excludedOutOfWindow: $outOfWindow,
            excludedWrongDate: $wrongDate,
            ownSquareCount: $ownSquare,
            excludedEmpty: $emptyWwl,
            duplicateCount: $duplicates,
            categoryId: $categoryId,
            categoryName: $categoryName,
        );
    }
}
