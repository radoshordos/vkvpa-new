<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QsoMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Jednotlivé spojení (QSO) v deníku EDI.
 *
 * Pozn.: řada sloupců má v původní DB nestandardní názvy (mezery, pomlčky,
 * závorky – např. `Mode-code`, `Sent QSO number`, `New-WWL-(N)`). Ponechány
 * kvůli kompatibilitě; přistupuje se k nim přes typované accessor metody.
 *
 * @property int $ID
 * @property int $IDS
 * @property string $Date
 * @property string $Time
 * @property string $CallSign
 * @property string $sqr
 * @property float|null $lon
 * @property float|null $lat
 * @property-read Edihead|null $head
 */
#[Fillable([
    'IDS', 'Date', 'Time', 'CallSign', 'Mode-code', 'Sent-RST',
    'Sent QSO number', 'Received-RST', 'Received QSO number',
    'Received exchange', 'Received-WWL', 'QSO-Points',
    'New-Exchange-(N)', 'New-WWL-(N)', 'New-DXCC-(N)',
    'Duplicate-QSO-(D)', 'sqr', 'lon', 'lat',
])]
#[Table(name: 'edilines', key: 'ID')]
#[WithoutTimestamps]
class Ediline extends Model
{
    // Legacy tabulka – property hooks přistupují přímo k $this->attributes['Column-name'],
    // ale ostatní kód přistupuje k reálným sloupcům přes __get; opt-out zachovává
    // kompatibilitu se starším přístupovým vzorem a tests s partial select.
    protected static $modelsShouldPreventAccessingMissingAttributes = false;

    /**
     * Hlavička deníku, ke kterému spojení patří.
     *
     * @return BelongsTo<Edihead, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Edihead::class, 'IDS', 'ID');
    }

    /** Přijatý lokátor protistanice (prázdný string pokud chybí). */
    public string $receivedWwl {
        get => trim((string) ($this->{'Received-WWL'} ?? ''));
    }

    /** Body za spojení z deníku (EDI QSO-Points; ve skóre se ignoruje). */
    public int $qsoPoints {
        get => (int) ($this->{'QSO-Points'} ?? 0);
    }

    /** Kód druhu provozu z deníku: 1 = SSB, 2 = CW, 0/jiné = neznámý. */
    public int $modeCode {
        get => (int) ($this->{'Mode-code'} ?? 0);
    }

    /** Druh provozu jako enum (neznámý/chybějící kód → QsoMode::Other). */
    public QsoMode $mode {
        get => QsoMode::fromCode($this->modeCode);
    }

    /** Opravený lokátor (New-WWL-(N), prázdný string pokud chybí). */
    public string $newWwl {
        get => trim((string) ($this->{'New-WWL-(N)'} ?? ''));
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'IDS' => 'integer',
            'Mode-code' => 'integer',
            'Sent QSO number' => 'integer',
            'Received QSO number' => 'integer',
            'QSO-Points' => 'integer',
            'sqr' => 'integer',
            'lon' => 'float',
            'lat' => 'float',
        ];
    }
}
