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
 * Vlastní časové sloupce (`stamp`, `d_cas`) nejsou Laravel created_at/updated_at.
 *
 * @property int $id
 * @property int|null $id_kola
 * @property string $t_date
 * @property string $p_call
 * @property string $p_wwlo
 * @property string $p_sect
 * @property string $p_band
 * @property string $r_name
 * @property string|null $r_emai
 * @property string $r_phon
 * @property int $s_powe
 * @property string|null $s_tx_eq
 * @property string|null $s_ante
 * @property string|null $src
 * @property string|null $remarks
 * @property string|null $s_rcr
 * @property Carbon|null $stamp
 * @property Carbon|null $d_cas
 * @property-read Collection<int, Ediline> $lines
 * @property-read int $lines_count
 */
#[Fillable([
    'id_kola', 't_date', 'p_call', 'p_wwlo', 'p_sect', 'p_band',
    'r_name', 'r_emai', 'r_phon', 's_powe', 's_tx_eq', 's_ante',
    'src', 'remarks', 's_rcr',
])]
#[Table(name: 'edi_head')]
#[WithoutTimestamps]
class Edihead extends Model
{
    /**
     * Jednotlivá spojení (QSO) tohoto deníku.
     *
     * @return HasMany<Ediline, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Ediline::class, 'edihead_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'id_kola' => 'integer',
            's_powe' => 'integer',
            'stamp' => 'datetime',
            'd_cas' => 'datetime',
        ];
    }
}
