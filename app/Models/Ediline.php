<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jednotlivé spojení (QSO) v deníku EDI.
 *
 * Pozn.: řada sloupců má v původní DB nestandardní názvy (mezery, pomlčky,
 * závorky – např. `Mode-code`, `Sent QSO number`, `New-WWL-(N)`). Ponechány
 * kvůli kompatibilitě; přistupuje se k nim přes $model->{'Mode-code'} nebo
 * accessory doplněné v pozdější fázi.
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
#[Table(name: 'edilines', key: 'ID')]
#[WithoutTimestamps]
class Ediline extends Model
{
    #[\Override]
    protected $guarded = [];

    /**
     * Hlavička deníku, ke kterému spojení patří.
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Edihead::class, 'IDS', 'ID');
    }
    #[\Override]
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
