<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VkvpaData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Spustí se po úspěšném importu EDI deníku a vytvoření řádku ve vkvpa_data.
 */
final class EdiImported
{
    use Dispatchable;

    public function __construct(
        public readonly VkvpaData $data,
    ) {}
}
