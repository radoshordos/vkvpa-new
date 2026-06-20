<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * Diskusní příspěvek navázaný na kolo závodu.
 *
 * @property int $id
 * @property int $kolo_id
 * @property string $znacka
 * @property string|null $jmeno
 * @property string $text
 * @property string|null $foto Cesta v disku 'public', např. diskuse/130/abc.jpg
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read VkvpaKola $kolo
 */
#[Fillable(['kolo_id', 'znacka', 'jmeno', 'text', 'foto', 'ip'])]
#[Table(name: 'diskuse', key: 'id')]
class Prispevek extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<VkvpaKola, $this> */
    public function kolo(): BelongsTo
    {
        return $this->belongsTo(VkvpaKola::class, 'kolo_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'kolo_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
