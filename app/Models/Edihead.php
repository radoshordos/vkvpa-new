<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hlavička deníku EDI (REG1TEST).
 *
 * Pozn.: tabulka i sloupce ponechány v původních názvech kvůli kompatibilitě.
 * Vlastní časové sloupce (`stamp`, `d_cas`) nejsou Laravel created_at/updated_at.
 */
#[Table(name: 'edihead', key: 'ID')]
#[WithoutTimestamps]
class Edihead extends Model
{
    #[\Override]
    protected $guarded = [];

    /**
     * Jednotlivá spojení (QSO) tohoto deníku.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Ediline::class, 'IDS', 'ID');
    }
    #[\Override]
    protected function casts(): array
    {
        return [
            'id_kola' => 'integer',
            'SPowe' => 'integer',
            'stamp' => 'datetime',
            'd_cas' => 'datetime',
        ];
    }
}
