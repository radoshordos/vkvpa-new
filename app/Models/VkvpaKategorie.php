<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategorie závodu.
 */
#[Table(name: 'vkvpa_kategorie', key: 'id')]
#[WithoutTimestamps]
class VkvpaKategorie extends Model
{
    #[\Override]
    protected $guarded = [];

    /** @return HasMany<VkvpaData, $this> */
    public function hlaseni(): HasMany
    {
        return $this->hasMany(VkvpaData::class, 'id_kategorie', 'id');
    }
    #[\Override]
    protected function casts(): array
    {
        return [
            'dxid' => 'integer',
        ];
    }
}
