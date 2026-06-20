<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Kolo odpovídající datu deníku už nepřijímá hlášení – upload okno
 * (den závodu 08:00 UTC až uzávěrka) je zavřené, nebo ještě nezačalo.
 */
final class UploadWindowClosedException extends RuntimeException
{
    public function __construct(string $nazevKola)
    {
        parent::__construct(
            "Kolo „{$nazevKola}\u{201c} právě nepřijímá hlášení – deník lze odeslat jen "
            .'v otevřeném upload okně (od dne závodu 08:00 UTC do uzávěrky).',
        );
    }
}
