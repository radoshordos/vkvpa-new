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
 * Jedna fotografie diskusního příspěvku, uložená binárně v DB.
 *
 * @property int $id
 * @property int $prispevek_id
 * @property string $mime MIME typ výstupního obrázku (např. image/jpeg)
 * @property string $data Binární data zmenšeného obrázku
 * @property string $nahled Binární data náhledu (thumbnail)
 * @property int $sirka
 * @property int $vyska
 * @property int $velikost Velikost hlavního obrázku v bajtech
 * @property int $poradi
 * @property Carbon $created_at
 * @property-read Prispevek $prispevek
 */
#[Fillable(['prispevek_id', 'mime', 'data', 'nahled', 'sirka', 'vyska', 'velikost', 'poradi'])]
#[Table(name: 'diskuse_foto', key: 'id')]
class PrispevekFoto extends Model
{
    public const UPDATED_AT = null;

    /**
     * Binární sloupce nikdy neserializujeme (toArray/toJson) – jsou velké a
     * patří jen do servírovací odpovědi.
     *
     * @var list<string>
     */
    protected $hidden = ['data', 'nahled'];

    /** @return BelongsTo<Prispevek, $this> */
    public function prispevek(): BelongsTo
    {
        return $this->belongsTo(Prispevek::class, 'prispevek_id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'prispevek_id' => 'integer',
            'sirka' => 'integer',
            'vyska' => 'integer',
            'velikost' => 'integer',
            'poradi' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
