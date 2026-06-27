<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Kategorie závodu, rozložená do os pásmo × sekce × varianta.
 *
 * Normalizovaná obdoba {@see VkvpaKategorie}: místo textové zkratky/názvu nese
 * dotazovatelné sloupce. `dxid` váže DX řádek na tuzemský protějšek
 * (stejné band+section, variant='domestic'); u tuzemských řádků je NULL.
 *
 * @property int $id
 * @property string $band pásmo s jednotkou ('144 MHz', '432 MHz', '1.3 GHz', … '122 GHz')
 * @property string $section 'SO' (single op) | 'MO' (multi op)
 * @property string $variant 'domestic' (tuzemská OK/OL) | 'dx' (zahraniční)
 * @property string $name čitelný název pro UI
 * @property int|null $dxid id tuzemského protějšku DX řádku; NULL = tato kategorie JE tuzemská
 */
#[Fillable(['id', 'band', 'section', 'variant', 'name', 'dxid'])]
#[Table(name: 'edi_category', key: 'id')]
#[WithoutTimestamps]
class EdiCategory extends Model
{
    public function isDx(): bool
    {
        return $this->variant === 'dx';
    }

    public function isMulti(): bool
    {
        return $this->section === 'MO';
    }

    /**
     * Tuzemský protějšek DX řádku (přes `dxid`).
     *
     * @return BelongsTo<self, $this>
     */
    public function domestic(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dxid', 'id');
    }

    /**
     * Tuzemský protějšek kategorie. Pro tuzemskou kategorii vrací sebe sama,
     * pro DX řádek dohledá řádek podle `dxid`; null jen když protějšek chybí.
     */
    public function domesticCounterpart(): ?self
    {
        if ($this->variant === 'domestic') {
            return $this;
        }

        return $this->dxid !== null ? self::find($this->dxid) : null;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'dxid' => 'integer',
        ];
    }
}
