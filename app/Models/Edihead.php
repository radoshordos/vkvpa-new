<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Hlavička deníku EDI (REG1TEST).
 *
 * Pozn.: tabulka i sloupce ponechány v původních názvech kvůli kompatibilitě.
 * Vlastní časové sloupce (`stamp`, `d_cas`) nejsou Laravel created_at/updated_at.
 *
 * @property int $ID
 * @property int|null $id_kola
 * @property string $TDate
 * @property string $PCall
 * @property string $PWWLo
 * @property int $SPowe
 * @property string|null $src
 * @property Carbon|null $stamp
 * @property Carbon|null $d_cas
 * @property string $PBand
 * @property-read Collection<int, Ediline> $lines
 * @property-read int $lines_count
 */
#[Fillable([
    'id_kola', 'TDate', 'PCall', 'PWWLo', 'PSect', 'PBand',
    'RName', 'REmai', 'RPhon', 'RHBBS', 'SPowe', 'STXEq', 'SAnte',
    'src', 'Remarks', 'SRCR',
])]
#[Table(name: 'edihead', key: 'ID')]
#[WithoutTimestamps]
class Edihead extends Model
{
    // Legacy tabulka s nestandardními názvy sloupců – preventAccessingMissingAttributes
    // by přístupy jako $head->{'PCall'} i reálné sloupce vyhodila v testech/dev.
    protected static $modelsShouldPreventAccessingMissingAttributes = false;

    /**
     * Jednotlivá spojení (QSO) tohoto deníku.
     *
     * @return HasMany<Ediline, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Ediline::class, 'IDS', 'ID');
    }

    #[Override]
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
