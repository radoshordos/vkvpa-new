<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Datum závodu v hlavičce EDI neodpovídá datům QSO řádků.
 */
final class TDateMismatchException extends RuntimeException
{
    public function __construct(string $tdate, string $qsoDates)
    {
        parent::__construct(
            "Datum závodu v hlavičce deníku (TDate={$tdate}) neodpovídá datům spojení ({$qsoDates}). "
            .'Oprav prosím TDate v hlavičce EDI souboru a nahraj ho znovu.',
        );
    }
}
