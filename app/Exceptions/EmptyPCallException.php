<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class EmptyPCallException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Deník neobsahuje volací značku (PCall) – bez ní nelze hlášení přiřadit závodníkovi. Oprav pole PCall v EDI souboru a nahraj znovu.',
        );
    }
}
