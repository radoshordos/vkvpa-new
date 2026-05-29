<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dočasné přihlašovací kódy (legacy mechanismus přihlášení přes ?kod=).
 *
 * Pozn.: ve Fázi 4 bude nahrazeno standardní Laravel autentizací; model je
 * zde pro zachování kompatibility během migrace.
 */
class VkvpaPrihlaseni extends Model
{
    protected $table = 'vkvpa_prihlaseni';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'time' => 'datetime',
    ];
}
