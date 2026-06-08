<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Pro datum závodu z hlavičky EDI (TDate) neexistuje žádné odpovídající kolo.
 */
final class RoundNotFoundException extends RuntimeException
{
    public function __construct(string $tdate)
    {
        parent::__construct(
            "Pro datum závodu z hlavičky deníku (TDate={$tdate}) neexistuje v systému žádné odpovídající kolo. "
            .'Zkontroluj prosím datum v hlavičce EDI souboru, nebo kolo zatím nebylo založeno.',
        );
    }
}
