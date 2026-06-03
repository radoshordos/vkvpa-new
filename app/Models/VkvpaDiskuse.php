<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Diskusní příspěvek navázaný na kolo závodu.
 */
#[Fillable(['id_kola', 'cas', 'znacka', 'jmeno', 'text', 'foto', 'ip'])]
#[Table(name: 'vkvpa_diskuse', key: 'id')]
#[WithoutTimestamps]
class VkvpaDiskuse extends Model
{
    /** @return BelongsTo<VkvpaKola, $this> */
    public function kolo(): BelongsTo
    {
        return $this->belongsTo(VkvpaKola::class, 'id_kola', 'id');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'id_kola' => 'integer',
            'cas' => 'datetime',
        ];
    }
}
