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
 * @property int $ID
 * @property int $IDS
 * @property string $Date
 * @property string $Time
 * @property string $CallSign
 * @property int|null $mode_code
 * @property string|null $sent_rst
 * @property int|null $sent_qso_number
 * @property string|null $received_rst
 * @property int|null $received_qso_number
 * @property string|null $received_exchange
 * @property string|null $received_wwl
 * @property int|null $qso_points
 * @property string|null $new_exchange_n
 * @property string|null $new_wwl_n
 * @property string|null $new_dxcc_n
 * @property string|null $duplicate_qso_d
 * @property int $sqr
 * @property float|null $lon
 * @property float|null $lat
 * @property-read Edihead|null $head
 */
#[Fillable([
    'IDS', 'Date', 'Time', 'CallSign', 'mode_code', 'sent_rst',
    'sent_qso_number', 'received_rst', 'received_qso_number',
    'received_exchange', 'received_wwl', 'qso_points',
    'new_exchange_n', 'new_wwl_n', 'new_dxcc_n',
    'duplicate_qso_d', 'sqr', 'lon', 'lat',
])]
#[Table(name: 'edilines', key: 'ID')]
#[WithoutTimestamps]
class Ediline extends Model
{
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
        get => trim((string) ($this->{'received_wwl'} ?? ''));
    }

    /** Body za spojení z deníku (EDI qso_points; ve skóre se ignoruje). */
    public int $qsoPoints {
        get => (int) ($this->{'qso_points'} ?? 0);
    }

    /** Kód druhu provozu z deníku: 1 = SSB, 2 = CW, 0/jiné = neznámý. */
    public int $modeCode {
        get => (int) ($this->{'mode_code'} ?? 0);
    }

    /** Druh provozu jako enum (neznámý/chybějící kód → QsoMode::Other). */
    public QsoMode $mode {
        get => QsoMode::fromCode($this->modeCode);
    }

    /** Opravený lokátor (new_wwl_n, prázdný string pokud chybí). */
    public string $newWwl {
        get => trim((string) ($this->{'new_wwl_n'} ?? ''));
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'IDS' => 'integer',
            'mode_code' => 'integer',
            'sent_qso_number' => 'integer',
            'received_qso_number' => 'integer',
            'qso_points' => 'integer',
            'sqr' => 'integer',
            'lon' => 'float',
            'lat' => 'float',
        ];
    }
}
