<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QsoCountStatus;
use App\Enums\QsoMode;
use App\Support\ContestWindow;
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
 * Jednotlivé spojení (QSO) v deníku EDI.
 *
 * @property int $id
 * @property int $edihead_id
 * @property Carbon|null $qso_at
 * @property string|null $call_sign
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
 * @property int|null $sqr
 * @property float|null $lon
 * @property float|null $lat
 * @property-read EdiHead|null $head
 */
#[Fillable([
    'edihead_id', 'qso_at', 'call_sign', 'mode_code', 'sent_rst',
    'sent_qso_number', 'received_rst', 'received_qso_number',
    'received_exchange', 'received_wwl', 'qso_points',
    'new_exchange_n', 'new_wwl_n', 'new_dxcc_n',
    'duplicate_qso_d', 'sqr', 'lon', 'lat',
])]
#[Table(name: 'edi_lines')]
#[WithoutTimestamps]
class EdiLine extends Model
{
    /**
     * Hlavička deníku, ke kterému spojení patří.
     *
     * @return BelongsTo<EdiHead, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(EdiHead::class, 'edihead_id');
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

    /** Čas QSO jako minuty od půlnoci (UTC); 0 když qso_at chybí. */
    public int $timeMinutes {
        get {
            $at = $this->qso_at?->utc();

            return $at === null ? 0 : $at->hour * 60 + $at->minute;
        }
    }

    /**
     * Scope: jen QSO uvnitř závodního časového okna (čas dne 08:00–11:00 UTC),
     * bez ohledu na den. Filtruje přes qso_at (`whereTime` je přenositelný mezi
     * MySQL a SQLite); řádky bez qso_at se nezapočítají.
     *
     * @param  Builder<EdiLine>  $query
     */
    #[Scope]
    protected function inContestWindow(Builder $query): void
    {
        $query
            ->whereTime('qso_at', '>=', ContestWindow::fromSqlTime())
            ->whereTime('qso_at', '<=', ContestWindow::toSqlTime());
    }

    /**
     * Scope: jen spojení s úplným přijatým kódem (přijatý RST i pořadové číslo).
     * Spojení, kde stanice nepřijala report nebo soutěžní kód, je dle pravidel
     * neplatné a do skóre se nezapočítává (shodně s
     * {@see QsoCountStatus::IncompleteExchange}).
     *
     * @param  Builder<EdiLine>  $query
     */
    #[Scope]
    protected function completeExchange(Builder $query): void
    {
        $query
            ->whereNotNull('received_qso_number')
            ->whereNotNull('received_rst')
            ->where('received_rst', '!=', '');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'edihead_id' => 'integer',
            'qso_at' => 'datetime',
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
