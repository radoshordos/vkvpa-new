<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Kategorie závodu, rozložená do os pásmo × sekce × varianta.
 *
 * Normalizovaná obdoba {@see VkvpaKategorie}: místo textové zkratky/názvu nese
 * tři dotazovatelné sloupce. Dvojice DX ↔ tuzemská kategorie se neukládá,
 * odvozuje se ze shodného band+section – viz {@see self::domesticCounterpart()}.
 *
 * @property int $id
 * @property string $band kanonický token pásma ('144', '432', '1.3', … '122')
 * @property string $section 'SO' (single op) | 'MO' (multi op)
 * @property string $variant 'domestic' (tuzemská OK/OL) | 'dx' (zahraniční)
 * @property string $name čitelný název pro UI
 */
#[Fillable(['id', 'band', 'section', 'variant', 'name'])]
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
     * Tuzemský protějšek DX kategorie (stejné pásmo i sekce, variant='domestic').
     * Pro tuzemskou kategorii vrací sebe sama; null jen když protějšek chybí
     * (např. pásmo bez tuzemské varianty).
     */
    public function domesticCounterpart(): ?self
    {
        return self::query()
            ->where('band', $this->band)
            ->where('section', $this->section)
            ->where('variant', 'domestic')
            ->first();
    }
}
