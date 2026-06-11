<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Scoring\ScoringService;
use App\Support\VkvpaSettings;
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
 * Pozn.: vlastní sloupec `timestamp` není Laravel created_at/updated_at.
 *
 * @property int $id
 * @property int $id_kola
 * @property int|null $id_kategorie
 * @property bool $qrp
 * @property bool $lp
 * @property string $znacka
 * @property string $locator
 * @property int $pocet
 * @property int $bodu_za_qso
 * @property int $nasobice
 * @property int $body
 * @property string $jmeno
 * @property string $mail
 * @property string $telefon
 * @property string $poznamka
 * @property string $soapbox
 * @property string $ip
 * @property int|null $edihead_id
 * @property int $poradi
 * @property bool $schvaleno
 * @property bool $odeslano
 * @property string $session_id
 * @property Carbon|null $timestamp
 * @property-read VkvpaKola|null $kolo
 * @property-read VkvpaKategorie|null $kategorie
 * @property-read Edihead|null $edihead
 *
 * Projekce z {@see ScoringService::yearlyResults()}:
 * @property-read int $kategorie_id
 * @property-read int|string $celkem
 * (`jmeno` je v projekci agregované přes MAX, typ sdílí s kmenovým sloupcem)
 */
#[Fillable([
    'id_kola', 'id_kategorie', 'qrp', 'lp', 'znacka', 'locator',
    'pocet', 'bodu_za_qso', 'nasobice', 'body', 'jmeno', 'mail',
    'telefon', 'poznamka', 'soapbox', 'ip', 'edihead_id',
    'poradi', 'schvaleno', 'odeslano', 'session_id',
])]
#[Table(name: 'vkvpa_data', key: 'id')]
#[WithoutTimestamps]
class VkvpaData extends Model
{
    /** @return BelongsTo<VkvpaKola, $this> */
    public function kolo(): BelongsTo
    {
        return $this->belongsTo(VkvpaKola::class, 'id_kola', 'id');
    }

    /** @return BelongsTo<VkvpaKategorie, $this> */
    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(VkvpaKategorie::class, 'id_kategorie', 'id');
    }

    /** @return BelongsTo<Edihead, $this> */
    public function edihead(): BelongsTo
    {
        return $this->belongsTo(Edihead::class, 'edihead_id');
    }

    /**
     * Scope: jen schválené záznamy (schvaleno = true).
     *
     * @param  Builder<VkvpaData>  $query
     * @return Builder<VkvpaData>
     */
    #[Scope]
    protected function approved(Builder $query): Builder
    {
        return $query->where('schvaleno', true);
    }

    /**
     * Scope: záznamy nahrané přes EDI soubor (mají vazbu na deník).
     *
     * @param  Builder<VkvpaData>  $query
     * @return Builder<VkvpaData>
     */
    #[Scope]
    protected function hasEdi(Builder $query): Builder
    {
        return $query->whereNotNull('edihead_id');
    }

    /**
     * Scope: čerstvé neschválené záznamy – drží kolo v „aktivním" stavu
     * ({@see VkvpaKola::isActive()}, {@see VkvpaKola::existujeAktivni()}).
     * Záznamy starší než `vkvpa.fresh_unapproved_days` (i ty bez timestampu)
     * aktivitu nedrží – jediný zapomenutý neschválený řádek by jinak navždy
     * blokoval veřejný přístup k EDI souborům a vizualizacím.
     *
     * @param  Builder<VkvpaData>  $query
     * @return Builder<VkvpaData>
     */
    #[Scope]
    protected function freshUnapproved(Builder $query): Builder
    {
        return $query
            ->where('schvaleno', false)
            ->where('timestamp', '>=', Carbon::now()->subDays(VkvpaSettings::freshUnapprovedDays()));
    }

    /**
     * Scope: průběžné výsledky kola (i nepřevzaté = stav „Čeká"), volitelně
     * filtrované kategorií, seřazené po kategoriích a bodech.
     * Sdíleno mezi formulářem hlášení a stránkou průběžných výsledků.
     *
     * @param  Builder<VkvpaData>  $query
     * @return Builder<VkvpaData>
     */
    #[Scope]
    protected function prubezne(Builder $query, int $idKola, ?int $idKategorie = null): Builder
    {
        return $query
            ->where('id_kola', $idKola)
            ->when($idKategorie, fn (Builder $q): Builder => $q->where('id_kategorie', $idKategorie))
            ->orderBy('id_kategorie')
            ->orderByDesc('body')
            ->orderByDesc('pocet');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'id_kola' => 'integer',
            'id_kategorie' => 'integer',
            'qrp' => 'boolean',
            'lp' => 'boolean',
            'pocet' => 'integer',
            'bodu_za_qso' => 'integer',
            'nasobice' => 'integer',
            'body' => 'integer',
            'edihead_id' => 'integer',
            'poradi' => 'integer',
            'schvaleno' => 'boolean',
            'odeslano' => 'boolean',
            'timestamp' => 'datetime',
        ];
    }
}
