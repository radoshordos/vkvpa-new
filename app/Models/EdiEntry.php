<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vykon;
use App\Services\Scoring\ScoringService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * Záznam hlášení do závodu (řádek výsledkové listiny pro dané kolo).
 *
 * Pozn.: vlastní sloupec `submitted_at` není Laravel created_at/updated_at.
 *
 * @property int $id
 * @property int $round_id
 * @property int|null $category_id
 * @property bool $qrp
 * @property bool $lp
 * @property string $callsign
 * @property string $locator
 * @property int $qso_count
 * @property int $qso_points
 * @property int $multiplier
 * @property int $points
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $note
 * @property string $soapbox
 * @property string $ip
 * @property int|null $edi_head_id
 * @property int $rank
 * @property bool $approved
 * @property bool $sent
 * @property string $session_id
 * @property Carbon|null $submitted_at
 * @property-read EdiRound|null $round
 * @property-read EdiCategory|null $category
 * @property-read Edihead|null $ediHead
 *
 * Projekce z {@see ScoringService::yearlyResults()}:
 * @property-read int $kategorie_id
 * @property-read int|string $celkem
 * (`name` je v projekci agregované přes MAX, typ sdílí s kmenovým sloupcem;
 *  `mesic_1`..`mesic_12` = body za měsíc, `vykon_1`..`vykon_12` = hodnota
 *  {@see Vykon}::value daného měsíce nebo null pro plný výkon)
 */
#[Fillable([
    'round_id', 'category_id', 'qrp', 'lp', 'callsign', 'locator',
    'qso_count', 'qso_points', 'multiplier', 'points', 'name', 'email',
    'phone', 'note', 'soapbox', 'ip', 'edi_head_id',
    'rank', 'approved', 'sent', 'session_id',
])]
#[Table(name: 'edi_entries', key: 'id')]
#[WithoutTimestamps]
class EdiEntry extends Model
{
    /** @return BelongsTo<EdiRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(EdiRound::class, 'round_id', 'id');
    }

    /** @return BelongsTo<EdiCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EdiCategory::class, 'category_id', 'id');
    }

    /** @return BelongsTo<Edihead, $this> */
    public function ediHead(): BelongsTo
    {
        return $this->belongsTo(Edihead::class, 'edi_head_id');
    }

    /**
     * Výkonová kategorie záznamu odvozená z příznaků qrp/lp (QRP ⊂ LP).
     * Jednotná reprezentace pro odznaky v listinách a podbarvení výsledků.
     */
    public function power(): Vykon
    {
        return Vykon::fromFlags($this->qrp, $this->lp);
    }

    /**
     * Scope: jen schválené záznamy (approved = true).
     *
     * @param  Builder<EdiEntry>  $query
     * @return Builder<EdiEntry>
     */
    #[Scope]
    protected function approved(Builder $query): Builder
    {
        return $query->where('approved', true);
    }

    /**
     * Scope: záznamy nahrané přes EDI soubor (mají vazbu na deník).
     *
     * @param  Builder<EdiEntry>  $query
     * @return Builder<EdiEntry>
     */
    #[Scope]
    protected function hasEdi(Builder $query): Builder
    {
        return $query->whereNotNull('edi_head_id');
    }

    /**
     * Scope: jen QRP stanice (≤5 W). Sloupec kvalifikujeme názvem tabulky,
     * aby scope fungoval i v dotazech s joinem (roční výsledky).
     *
     * @param  Builder<EdiEntry>  $query
     * @return Builder<EdiEntry>
     */
    #[Scope]
    protected function onlyQrp(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('qrp'), true);
    }

    /**
     * Scope: jen nízkovýkonové stanice. QRP (≤5 W) je podmnožinou LP (<100 W),
     * proto „jen LP" zahrnuje i QRP stanice.
     *
     * @param  Builder<EdiEntry>  $query
     * @return Builder<EdiEntry>
     */
    #[Scope]
    protected function onlyLp(Builder $query): Builder
    {
        return $query->where(fn (Builder $w): Builder => $w
            ->where($this->qualifyColumn('lp'), true)
            ->orWhere($this->qualifyColumn('qrp'), true));
    }

    /**
     * Scope: průběžné výsledky kola (i nepřevzaté = stav „Čeká"), volitelně
     * filtrované kategorií, seřazené po kategoriích a bodech.
     * Sdíleno mezi formulářem hlášení a stránkou průběžných výsledků.
     *
     * @param  Builder<EdiEntry>  $query
     * @return Builder<EdiEntry>
     */
    #[Scope]
    protected function standings(Builder $query, int $roundId, ?int $categoryId = null): Builder
    {
        return $query
            ->where('round_id', $roundId)
            ->when($categoryId, fn (Builder $q): Builder => $q->where('category_id', $categoryId))
            ->orderBy('category_id')
            ->orderByDesc('points')
            ->orderByDesc('qso_count');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'round_id' => 'integer',
            'category_id' => 'integer',
            'qrp' => 'boolean',
            'lp' => 'boolean',
            'qso_count' => 'integer',
            'qso_points' => 'integer',
            'multiplier' => 'integer',
            'points' => 'integer',
            'edi_head_id' => 'integer',
            'rank' => 'integer',
            'approved' => 'boolean',
            'sent' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }
}
