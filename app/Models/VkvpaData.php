<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 */
#[Table(name: 'vkvpa_data', key: 'id')]
#[WithoutTimestamps]
class VkvpaData extends Model
{
    #[\Override]
    protected $guarded = [];

    public function kolo(): BelongsTo
    {
        return $this->belongsTo(VkvpaKola::class, 'id_kola', 'id');
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(VkvpaKategorie::class, 'id_kategorie', 'id');
    }

    public function edihead(): BelongsTo
    {
        return $this->belongsTo(Edihead::class, 'EDI_ID', 'ID');
    }
    #[\Override]
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
