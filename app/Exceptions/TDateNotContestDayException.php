<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Datum závodu v hlavičce EDI neodpovídá termínu kola.
 *
 * Závod VKV PA se koná vždy třetí neděli v měsíci. TDate může být i dvoudenní
 * rozsah (start;end), pokud účastník použil šablonu 24h závodu – stačí proto,
 * aby třetí neděli odpovídalo alespoň jedno z dat uvedených v TDate.
 */
final class TDateNotContestDayException extends RuntimeException
{
    public function __construct(string $tdate)
    {
        parent::__construct(
            "Datum závodu v hlavičce deníku (TDate={$tdate}) neodpovídá termínu kola. "
            .'Závod VKV PA se koná vždy třetí neděli v měsíci – oprav prosím TDate v hlavičce EDI souboru a nahraj ho znovu.',
        );
    }
}
