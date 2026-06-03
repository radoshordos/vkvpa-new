<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\Edihead;
use App\Models\Ediline;
use Illuminate\Support\Facades\DB;

/**
 * Uloží naparsovaný EDI deník do DB (edihead + edilines) v jedné transakci.
 */
final class EdiImportService
{
    public function import(EdiLog $log): Edihead
    {
        return DB::transaction(function () use ($log): Edihead {
            $h = $log->header;

            $head = Edihead::create([
                'TDate' => $h->tDate(),
                'PCall' => $h->pCall(),
                'PWWLo' => $h->pWWLo(),
                'PSect' => $h->pSect(),
                'PBand' => $h->pBand(),
                'RName' => $h->rName(),
                'RPhon' => $h->rPhon(),
                'RHBBS' => $h->rHBBS(),
                'SPowe' => $h->sPowe(),
                'src' => $log->rawSource,
            ]);

            $rows = [];
            foreach ($log->qsos as $q) {
                $rows[] = [
                    'IDS' => $head->ID,
                    'Date' => $q->date,
                    'Time' => $q->time,
                    'CallSign' => $q->callSign,
                    'Mode-code' => $this->intOrNull($q->modeCode),
                    'Sent-RST' => $q->sentRst,
                    'Sent QSO number' => $this->intOrNull($q->sentQsoNumber),
                    'Received-RST' => $q->receivedRst,
                    'Received QSO number' => $this->intOrNull($q->receivedQsoNumber),
                    'Received exchange' => $q->receivedExchange,
                    'Received-WWL' => $q->receivedWwl,
                    'QSO-Points' => $this->intOrNull($q->qsoPoints),
                    'New-Exchange-(N)' => $q->newExchange,
                    'New-WWL-(N)' => $q->newWwl,
                    'New-DXCC-(N)' => $q->newDxcc,
                    'Duplicate-QSO-(D)' => $q->duplicate,
                ];
            }

            if ($rows !== []) {
                Ediline::insert($rows);
            }

            return $head;
        });
    }

    private function intOrNull(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }
}
