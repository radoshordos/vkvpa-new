<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Scoring\ScoringService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Záznam hlášení do závodu (řádek výsledkové listiny pro dané kolo).
 *
 * Pozn.: vlastní sloupec `timestamp` není Laravel created_at/updated_at.
 *
 * @property int $id
 * @property int $id_kola
 * @property int $id_kategorie
 * @property bool $qrp
 * @property int $lp
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
 * @property bool $EDI
 * @property int $EDI_ID
 * @property int $poradi
 * @property bool $schvaleno
 * @property string|null $odeslano
 * @property string $session_id
 * @property string|null $timestamp
 * @property-read VkvpaKola|null $kolo
 * @property-read VkvpaKategorie|null $kategorie
 * @property-read Edihead|null $edihead
 *
 * Projekce z {@see ScoringService::yearlyResults()}:
 * @property-read int $kategorie_id
 * @property-read int|string $celkem
 */
#[Fillable([
    'id_kola', 'id_kategorie', 'qrp', 'lp', 'znacka', 'locator',
    'pocet', 'bodu_za_qso', 'nasobice', 'body', 'jmeno', 'mail',
    'telefon', 'poznamka', 'soapbox', 'ip', 'EDI', 'EDI_ID',
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
        return $this->belongsTo(Edihead::class, 'EDI_ID', 'ID');
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
     * Scope: záznamy nahrané přes EDI soubor (EDI = true).
     *
     * @param  Builder<VkvpaData>  $query
     * @return Builder<VkvpaData>
     */
    #[Scope]
    protected function hasEdi(Builder $query): Builder
    {
        return $query->where('EDI', true);
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
            'EDI' => 'boolean',
            'EDI_ID' => 'integer',
            'poradi' => 'integer',
            'schvaleno' => 'boolean',
            'odeslano' => 'boolean',
            'timestamp' => 'datetime',
        ];
    }
}
