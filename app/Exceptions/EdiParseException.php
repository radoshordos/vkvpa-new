<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Chyba při parsování EDI deníku (nesoulad počtu QSO, vadné řádky apod.).
 */
class EdiParseException extends RuntimeException
{
    /**
     * @param list<string> $lineErrors
     */
    public function __construct(string $message, public readonly array $lineErrors = [])
    {
        parent::__construct($message);
    }
}
