<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Výsledek automatického ohodnocení deníku z edilines.
 *
 * Rekonstrukce původního DB pohledu `vysledky` (lbody, lnasobic, platnych),
 * jehož definice nebyla v repozitáři/dumpu. Mapování na legacy:
 *   bodu_za_qso = lbody    (součet QSO-Points)
 *   nasobice    = lnasobic (počet násobičů = různé velké čtverce WWL)
 *   pocet       = platnych (počet platných QSO)
 *   body        = lbody * lnasobic
 */
final readonly class EdiScore
{
    public function __construct(
        public int $lbody,
        public int $lnasobic,
        public int $platnych,
    ) {
    }

    public function body(): int
    {
        return $this->lbody * $this->lnasobic;
    }
}
