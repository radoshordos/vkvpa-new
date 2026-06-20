<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * Kategorie závodu.
 *
 * @property int $id
 * @property string $nazev
 * @property string $popis
 * @property string $zkratka
 * @property int $dxid ID odpovídající tuzemské (OK/OL) kategorie; 0 = tato kategorie JE tuzemská.
 *                     Příklad: kategorie id=23 „144 MHz single DX" má dxid=1 → id=1 „144 MHz single op".
 *                     Slouží pro párování DX a tuzemských výsledků v administraci.
 */
#[Fillable(['nazev', 'popis', 'zkratka', 'dxid'])]
#[Table(name: 'vkvpa_kategorie', key: 'id')]
#[WithoutTimestamps]
class VkvpaKategorie extends Model
{
    /** @return HasMany<VkvpaData, $this> */
    public function hlaseni(): HasMany
    {
        return $this->hasMany(VkvpaData::class, 'id_kategorie', 'id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'dxid' => 'integer',
        ];
    }
}
