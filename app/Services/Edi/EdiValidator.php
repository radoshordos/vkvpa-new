<?php

declare(strict_types=1);

namespace App\Services\Edi;

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
    /**
     * @param  ?string  $contestDay  den závodu „YYMMDD" (z data konání kola); když
     *                               null, odvodí se z TDate (fallback). Volající,
     *                               který zná kolo, ho předá, aby se počty QSO
     *                               „mimo den" shodly se skutečným skóre.
     */
    public function validate(EdiLog $log, ?string $contestDay = null): EdiValidationReport
    {
        return EdiValidationReport::fromLog($log, $contestDay);
    }
}
