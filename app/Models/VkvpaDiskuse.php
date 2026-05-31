<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diskusní příspěvek navázaný na kolo závodu.
 */
#[Table(name: 'vkvpa_diskuse', key: 'id')]
#[WithoutTimestamps]
class VkvpaDiskuse extends Model
{
    #[\Override]
    protected $guarded = [];

    /** @return BelongsTo<VkvpaKola, $this> */
    public function kolo(): BelongsTo
    {
        return $this->belongsTo(VkvpaKola::class, 'id_kola', 'id');
    }
    #[\Override]
    protected function casts(): array
    {
        return [
            'id_kola' => 'integer',
            'cas' => 'datetime',
        ];
    }
}
