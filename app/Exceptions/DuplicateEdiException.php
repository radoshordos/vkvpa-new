<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Deník stanice pro toto kolo již existuje.
 */
final class DuplicateEdiException extends RuntimeException
{
    public function __construct(string $pcall)
    {
        parent::__construct("Deník stanice {$pcall} už byl pro toto kolo nahrán – soubor již existuje.");
    }
}
