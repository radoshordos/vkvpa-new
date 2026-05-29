<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diskusní příspěvek navázaný na kolo závodu.
 */
class VkvpaDiskuse extends Model
{
    protected $table = 'vkvpa_diskuse';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'id_kola' => 'integer',
        'cas' => 'datetime',
    ];

    public function kolo(): BelongsTo
    {
        return $this->belongsTo(VkvpaKola::class, 'id_kola', 'id');
    }
}
