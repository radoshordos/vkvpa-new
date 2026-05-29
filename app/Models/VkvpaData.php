<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Záznam hlášení do závodu (řádek výsledkové listiny pro dané kolo).
 *
 * Pozn.: vlastní sloupec `timestamp` není Laravel created_at/updated_at.
 */
class VkvpaData extends Model
{
    protected $table = 'vkvpa_data';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
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
}
