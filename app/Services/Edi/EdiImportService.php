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
                    'mode_code' => $this->intOrNull($q->modeCode),
                    'sent_rst' => $q->sentRst,
                    'sent_qso_number' => $this->intOrNull($q->sentQsoNumber),
                    'received_rst' => $q->receivedRst,
                    'received_qso_number' => $this->intOrNull($q->receivedQsoNumber),
                    'received_exchange' => $q->receivedExchange,
                    'received_wwl' => $q->receivedWwl,
                    'qso_points' => $this->intOrNull($q->qsoPoints),
                    'new_exchange_n' => $q->newExchange,
                    'new_wwl_n' => $q->newWwl,
                    'new_dxcc_n' => $q->newDxcc,
                    'duplicate_qso_d' => $q->duplicate,
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
