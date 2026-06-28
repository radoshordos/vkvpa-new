<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Override;

/**
 * Kategorie závodu, rozložená do os pásmo × sekce × varianta. Jediný číselník
 * kategorií aplikace (dříve duplicitní `vkvpa_kategorie` byla zrušena).
 *
 * Místo textové zkratky/názvu nese dotazovatelné sloupce; pro zpětnou
 * kompatibilitu nabízí čtené atributy `nazev` (= `name`), `band` (název pásma
 * z číselníku `edi_bands`) a `zkratka` (generovaná z os). `dxid` váže DX řádek
 * na tuzemský protějšek (stejné band+section, variant='domestic'); u tuzemských
 * řádků je NULL.
 *
 * Pásmo je normalizované: `band_id` (FK → `edi_bands`) je jediný zdroj pravdy,
 * název se čte přes relaci `ediBand` (čtený atribut `band`). U syntetických
 * (testovacích) řádků s neznámým pásmem je `band_id` NULL.
 *
 * @property int $id
 * @property int|null $band_id FK → edi_bands.id (číselník pásem); NULL u neznámého pásma
 * @property string $section 'SO' (single op) | 'MO' (multi op)
 * @property string $variant 'domestic' (tuzemská OK/OL) | 'dx' (zahraniční)
 * @property string $name čitelný název pro UI
 * @property int|null $dxid id tuzemského protějšku DX řádku; NULL = tato kategorie JE tuzemská
 * @property-read string $band název pásma z `edi_bands` ('144 MHz', '1.3 GHz', …); čtený přes band_id
 * @property-read string $nazev alias pro `name` (zpětná kompatibilita)
 * @property-read string $zkratka generovaná zkratka ('144 SO', '144 SO DX')
 * @property-read EdiBand|null $ediBand pásmo z číselníku (přes band_id)
 */
#[Fillable(['id', 'band_id', 'section', 'variant', 'name', 'dxid'])]
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
     * Hlášení (záznamy listiny) v této kategorii.
     *
     * @return HasMany<EdiEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(EdiEntry::class, 'category_id', 'id');
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
     * Pásmo kategorie z číselníku `edi_bands`.
     *
     * @return BelongsTo<EdiBand, $this>
     */
    public function ediBand(): BelongsTo
    {
        return $this->belongsTo(EdiBand::class, 'band_id', 'id');
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

    /**
     * Alias `name` pod historickým názvem `nazev`.
     *
     * @return Attribute<string, never>
     */
    protected function nazev(): Attribute
    {
        return Attribute::get(fn (): string => (string) $this->name);
    }

    /**
     * Název pásma ('144 MHz', '1.3 GHz', …). Pokud byl dotaz JOINnut na
     * `edi_bands` a alias `band` je v atributech (hromadné dotazy), vrátí ho
     * přímo; jinak dohledá přes relaci `ediBand` (band_id). U neznámého pásma
     * (band_id NULL) vrací prázdný řetězec.
     *
     * @return Attribute<string, never>
     */
    protected function band(): Attribute
    {
        return Attribute::get(function (mixed $value, array $attributes): string {
            // Hromadné dotazy JOINují edi_bands a aliasují název jako 'band'
            // (i NULL u neznámého pásma) – v tom případě relaci vůbec neřešíme.
            if (array_key_exists('band', $attributes)) {
                return is_string($value) ? $value : '';
            }

            if ($this->band_id === null) {
                return '';
            }

            // Samostatný model: pokud je relace načtená, vezmi ji; jinak
            // explicitní dotaz přes relaci (ne lazy-load, projde i s
            // preventLazyLoading v testech).
            if ($this->relationLoaded('ediBand')) {
                return (string) $this->ediBand?->name;
            }

            $name = $this->ediBand()->value('name');

            return is_string($name) ? $name : '';
        });
    }

    /**
     * Generovaná zkratka z os, např. '144 SO' / '144 SO DX'.
     *
     * @return Attribute<string, never>
     */
    protected function zkratka(): Attribute
    {
        return Attribute::get(function (): string {
            $token = explode(' ', (string) $this->band)[0]; // '144 MHz' → '144', '1.3 GHz' → '1.3'
            $dx = $this->variant === 'dx' ? ' DX' : '';

            return trim("{$token} {$this->section}{$dx}");
        });
    }

    /**
     * Mapa id → zkratka (pro místa, kde generovaný atribut nejde plucknout v SQL).
     *
     * @return Collection<int, string>
     */
    public static function zkratkaMap(): Collection
    {
        return self::query()
            ->leftJoin('edi_bands', 'edi_bands.id', '=', 'edi_category.band_id')
            ->get(['edi_category.id', 'edi_bands.name as band', 'edi_category.section', 'edi_category.variant'])
            ->mapWithKeys(static fn (self $c): array => [$c->id => $c->zkratka]);
    }

    /**
     * Mapa id → název.
     *
     * @return Collection<int, string>
     */
    public static function nazevMap(): Collection
    {
        return self::query()->get(['id', 'name'])
            ->mapWithKeys(static fn (self $c): array => [$c->id => (string) $c->name]);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'band_id' => 'integer',
            'dxid' => 'integer',
        ];
    }
}
