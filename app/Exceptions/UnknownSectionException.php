<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Sekce v hlavičce EDI (`PSect`) je prázdná nebo nerozpoznaná – nelze určit kategorii.
 * Deník se odmítne; uživatel musí doplnit PSect na SINGLE nebo MULTI a nahrát znovu.
 */
class UnknownSectionException extends RuntimeException
{
    public function __construct(string $pSect)
    {
        $value = trim($pSect) === '' ? '(prázdné)' : '"'.$pSect.'"';

        parent::__construct(
            "Nerozpoznaná sekce PSect: {$value}. Doplňte PSect na SINGLE nebo MULTI v hlavičce EDI souboru a nahrajte znovu.",
        );
    }
}
