<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategorie závodu.
 */
class VkvpaKategorie extends Model
{
    protected $table = 'vkvpa_kategorie';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'dxid' => 'integer',
    ];

    public function hlaseni(): HasMany
    {
        return $this->hasMany(VkvpaData::class, 'id_kategorie', 'id');
    }
}
