<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * Diskusní příspěvek navázaný na kolo závodu.
 *
 * @property int $id
 * @property int $round_id
 * @property string $znacka
 * @property string|null $jmeno
 * @property string $text
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read EdiRound $round
 * @property-read Collection<int, PrispevekFoto> $fotky
 */
#[Fillable(['round_id', 'znacka', 'jmeno', 'text', 'ip'])]
#[Table(name: 'diskuse', key: 'id')]
class Prispevek extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<EdiRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(EdiRound::class, 'round_id');
    }

    /** @return HasMany<PrispevekFoto, $this> */
    public function fotky(): HasMany
    {
        return $this->hasMany(PrispevekFoto::class, 'prispevek_id')->orderBy('poradi');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'round_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
