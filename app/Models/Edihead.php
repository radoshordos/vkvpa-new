<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Hlavička deníku EDI (REG1TEST).
 *
 * Vlastní časové sloupce (`stamp`, `d_cas`) nejsou Laravel created_at/updated_at.
 *
 * @property int $id
 * @property int|null $round_id
 * @property int|null $edi_category_id
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
 * @property-read EdiCategory|null $category
 */
#[Fillable([
    'round_id', 'edi_category_id', 't_date', 'p_call', 'p_wwlo', 'p_sect', 'p_band',
    'r_name', 'r_emai', 'r_phon', 's_powe', 's_tx_eq', 's_ante',
    'src', 'remarks', 's_rcr',
])]
#[Table(name: 'edi_heads')]
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

    /**
     * Normalizovaná kategorie deníku (band × section × variant).
     *
     * @return BelongsTo<EdiCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EdiCategory::class, 'edi_category_id', 'id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'round_id' => 'integer',
            'edi_category_id' => 'integer',
            's_powe' => 'integer',
            'stamp' => 'datetime',
            'd_cas' => 'datetime',
        ];
    }
}
