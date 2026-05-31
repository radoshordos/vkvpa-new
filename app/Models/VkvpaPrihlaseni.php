<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Dočasné přihlašovací kódy (legacy mechanismus přihlášení přes ?kod=).
 *
 * Pozn.: ve Fázi 4 bude nahrazeno standardní Laravel autentizací; model je
 * zde pro zachování kompatibility během migrace.
 */
#[Table(name: 'vkvpa_prihlaseni', key: 'id')]
#[WithoutTimestamps]
class VkvpaPrihlaseni extends Model
{
    #[\Override]
    protected $guarded = [];

    #[\Override]
    protected function casts(): array
    {
        return [
            'time' => 'datetime',
        ];
    }
}
