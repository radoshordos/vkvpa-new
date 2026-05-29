<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hlavička deníku EDI (REG1TEST).
 *
 * Pozn.: tabulka i sloupce ponechány v původních názvech kvůli kompatibilitě.
 * Vlastní časové sloupce (`stamp`, `d_cas`) nejsou Laravel created_at/updated_at.
 */
class Edihead extends Model
{
    protected $table = 'edihead';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'id_kola' => 'integer',
        'SPowe' => 'integer',
        'stamp' => 'datetime',
        'd_cas' => 'datetime',
    ];

    /**
     * Jednotlivá spojení (QSO) tohoto deníku.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Ediline::class, 'IDS', 'ID');
    }
}
