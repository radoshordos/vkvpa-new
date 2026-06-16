<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Edi\EdiValidationReport;
use App\Services\Scoring\EdiScoreDebugger;
use App\Services\Scoring\ScoringService;

/**
 * Stav započítání jednoho QSO do skóre dle pravidel VKV PA.
 *
 * Kanonické pořadí vylučovacích důvodů (čas → den → prázdný velký čtverec →
 * započítá se) je sdíleno všemi místy, která QSO klasifikují nad naparsovaným
 * deníkem: {@see ScoringService::scoreLog()},
 * {@see EdiScoreDebugger}, {@see EdiValidationReport}.
 * Hodnoty (`out_of_window`, …) jsou strojové kódy zobrazené v rozpadu bodování.
 */
enum QsoCountStatus: string
{
    case OutOfWindow = 'out_of_window';
    case WrongDate = 'wrong_date';
    case EmptyWwl = 'empty_wwl';
    case Counted = 'counted';

    /**
     * Klasifikuje QSO v kanonickém pořadí: čas mimo závodní okno → jiný den
     * než závod → prázdný/neplatný přijatý velký čtverec → započítá se.
     *
     * @param  string  $time  čas QSO „HHMM"
     * @param  string  $date  datum QSO „YYMMDD"
     * @param  string  $bigSquare  už spočtený velký čtverec přijatého lokátoru ('' = prázdný)
     * @param  string  $contestDay  den závodu „YYMMDD" ('' = den se nekontroluje)
     * @param  string  $from  začátek závodního okna „HHMM"
     * @param  string  $to  konec závodního okna „HHMM"
     */
    public static function classify(string $time, string $date, string $bigSquare, string $contestDay, string $from, string $to): self
    {
        $time = trim($time);

        if ($time < $from || $time > $to) {
            return self::OutOfWindow;
        }

        if ($contestDay !== '' && trim($date) !== $contestDay) {
            return self::WrongDate;
        }

        if ($bigSquare === '') {
            return self::EmptyWwl;
        }

        return self::Counted;
    }

    public function isCounted(): bool
    {
        return $this === self::Counted;
    }
}
