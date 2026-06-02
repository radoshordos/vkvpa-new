<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Závodní časové okno (UTC) – QSO mimo se nezapočítávají.
 *
 * Legacy pravidlo: `time BETWEEN 0800 AND 1100`
 */
final class ContestWindow
{
    public const string FROM = '0800';

    public const string TO = '1100';
}
