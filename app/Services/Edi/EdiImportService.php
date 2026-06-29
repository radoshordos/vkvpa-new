<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\EdiHead;
use App\Models\EdiLine;
use Illuminate\Support\Facades\DB;

/**
 * Uloží naparsovaný EDI deník do DB (edihead + edilines) v jedné transakci.
 */
final class EdiImportService
{
    public function import(EdiLog $log, ?int $idKola = null): EdiHead
    {
        return DB::transaction(function () use ($log, $idKola): EdiHead {
            $h = $log->header;

            $head = EdiHead::create([
                'round_id' => $idKola,
                't_date' => $h->tDate(),
                'p_call' => $h->pCall(),
                'p_wwlo' => $h->pWWLo(),
                'p_sect' => $h->pSect(),
                'p_band' => $h->pBand(),
                'r_name' => $h->rName(),
                'r_emai' => $h->rEmail(),
                'r_phon' => $h->rPhon(),
                's_powe' => $h->sPowe(),
                'src' => $log->rawSource,
            ]);

            $rows = [];
            foreach ($log->qsos as $q) {
                $rows[] = [
                    'edihead_id' => $head->id,
                    'qso_at' => $q->qsoAt(),
                    'call_sign' => $q->callSign,
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
                EdiLine::insert($rows);
            }

            return $head;
        });
    }

    private function intOrNull(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }
}
